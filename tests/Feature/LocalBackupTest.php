<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\PlatformOperationActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/medloby-backup-'.Str::uuid();
    File::makeDirectory($this->directory, 0700);
    File::put($this->directory.'/source.sqlite', '');
    $this->sourcePrivate = $this->directory.'/source-private';
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => $this->directory.'/source.sqlite',
        'filesystems.disks.private.root' => $this->sourcePrivate,
        'filesystems.disks.local.root' => $this->sourcePrivate,
    ]);
    DB::purge('sqlite');
    Storage::forgetDisk(['private', 'local']);
    $this->artisan('migrate')->assertSuccessful();
});

afterEach(function () {
    DB::purge('sqlite');
    Storage::forgetDisk(['private', 'local']);
    File::deleteDirectory($this->directory);
});

function createLocalBackupAttachment(User $patient, string $disk = 'private'): MessageAttachment
{
    $profile = $patient->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $conversation = Conversation::create([
        'business_id' => $business->id, 'branch_id' => $branch->id,
        'patient_profile_id' => $profile->id, 'subject' => 'Yedek provası', 'status' => 'open',
    ]);
    $message = $conversation->messages()->create([
        'sender_user_id' => $patient->id, 'body' => 'Yedeklenecek test mesajı', 'message_type' => 'file',
    ]);

    return $message->attachments()->create([
        'disk' => $disk, 'path' => 'conversations/'.$conversation->id.'/sample.txt',
        'original_name' => 'sample.txt', 'mime_type' => 'text/plain', 'size' => 12,
    ]);
}

test('a backup restores a SQLite snapshot and private attachment with the original access rules', function () {
    expect(DB::selectOne('PRAGMA journal_mode = WAL')->journal_mode)->toBe('wal');
    $patient = User::factory()->create(['name' => 'Yedekteki Ad']);
    $attachment = createLocalBackupAttachment($patient);
    Storage::disk('private')->put($attachment->path, 'Deneme dosya');
    $backup = $this->directory.'/backup';
    $restored = $this->directory.'/restored';

    $this->artisan('medloby:backup', ['destination' => $backup])->assertSuccessful();
    expect(app(PlatformOperationActivity::class)->lastObserved()['backup_verification'])->not->toBeNull();
    expect(fileperms($backup) & 0077)->toBe(0);
    if (PHP_OS_FAMILY !== 'Windows') {
        foreach (['manifest.json', 'database.sqlite', 'storage/app/private/'.$attachment->path] as $file) {
            expect(fileperms($backup.'/'.$file) & 0777)->toBe(0600);
        }
    }
    $patient->update(['name' => 'Yedekten sonra değişen ad']);
    Storage::disk('private')->put($attachment->path, 'Değişen dosya');
    $this->artisan('medloby:restore', ['backup' => $backup, 'destination' => $restored])->assertSuccessful();
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($restored.'/database.sqlite') & 0777)->toBe(0600)
            ->and(fileperms($restored.'/storage/app/private/'.$attachment->path) & 0777)->toBe(0600);
    }
    $this->assertDatabaseHas('users', ['id' => $patient->id, 'name' => 'Yedekten sonra değişen ad']);
    expect(Storage::disk('private')->get($attachment->path))->toBe('Değişen dosya');

    config([
        'database.connections.sqlite.database' => $restored.'/database.sqlite',
        'filesystems.disks.private.root' => $restored.'/storage/app/private',
        'filesystems.disks.local.root' => $restored.'/storage/app/private',
    ]);
    DB::purge('sqlite');
    Storage::forgetDisk(['private', 'local']);
    $this->assertDatabaseHas('users', ['id' => $patient->id, 'name' => 'Yedekteki Ad']);

    $lastMigration = DB::table('migrations')->latest('id')->value('migration');
    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
    $this->assertDatabaseMissing('migrations', ['migration' => $lastMigration]);
    $this->artisan('migrate')->assertSuccessful();
    $this->assertDatabaseHas('migrations', ['migration' => $lastMigration]);

    $url = '/api/attachments/'.$attachment->id.'/view';
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertForbidden();
    $download = $this->actingAs(User::findOrFail($patient->id), 'sanctum')->get($url)->assertOk();
    expect($download->streamedContent())->toBe('Deneme dosya');
});

test('restore preserves existing directories and rejects a corrupted backup without leaving a partial restore', function () {
    $backup = $this->directory.'/backup';
    $existing = $this->directory.'/existing';
    $target = $this->directory.'/refused';
    File::makeDirectory($existing);
    File::put($existing.'/keep.txt', 'Korunacak dosya');
    Storage::disk('private')->put('example.txt', 'Yedek dosya');
    $this->artisan('medloby:backup', ['destination' => $backup])->assertSuccessful();

    $this->artisan('medloby:restore', ['backup' => $backup, 'destination' => $existing])
        ->expectsOutput('Hedef dizin mevcut veya oluşturulamıyor.')->assertFailed();
    expect(File::get($existing.'/keep.txt'))->toBe('Korunacak dosya');
    File::put($backup.'/storage/app/private/example.txt', 'Bozuk dosya');
    $this->artisan('medloby:restore', ['backup' => $backup, 'destination' => $target])
        ->expectsOutput('Yedekteki dosyalar doğrulama toplamlarıyla eşleşmiyor.')->assertFailed();
    expect(File::exists($target))->toBeFalse();
});

test('backup refuses linked files that are missing and destinations served by the web server', function () {
    $missing = createLocalBackupAttachment(User::factory()->create(), 'local');
    $backup = $this->directory.'/backup';
    $public = public_path('medloby-backup-'.Str::uuid());

    $this->artisan('medloby:backup', ['destination' => $backup])
        ->expectsOutput('Yedekte veritabanına bağlı bir görüşme dosyası eksik.')->assertFailed();
    expect(File::exists($backup))->toBeFalse();
    Storage::disk('local')->put($missing->path, 'Eski disk dosyası');
    $this->artisan('medloby:backup', ['destination' => $backup])->assertSuccessful();
    $this->artisan('medloby:restore', ['backup' => $backup, 'destination' => $public])
        ->expectsOutput('Yedekleme ve geri yükleme için kaynak ve web dizinlerinin dışında bir hedef seçin.')->assertFailed();
    expect(File::exists($public))->toBeFalse();

    $publicDisk = $this->directory.'/public-disk';
    File::makeDirectory($publicDisk);
    config(['filesystems.disks.public.root' => $publicDisk]);
    $this->artisan('medloby:restore', ['backup' => $backup, 'destination' => $publicDisk.'/restored'])
        ->expectsOutput('Yedekleme ve geri yükleme için kaynak ve web dizinlerinin dışında bir hedef seçin.')->assertFailed();
    expect(File::exists($publicDisk.'/restored'))->toBeFalse();
});
