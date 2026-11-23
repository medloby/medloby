<?php

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    if (DB::getDefaultConnection() !== 'pgsql') {
        $this->markTestSkipped('Bu prova PostgreSQL yedekleme araçları ve bir PostgreSQL test bağlantısı gerektirir.');
    }

    $this->createdDatabases = [];
    $this->postgres = DB::connection('pgsql');
    $this->directory = sys_get_temp_dir().'/medloby-postgres-backup-'.Str::uuid();
    File::makeDirectory($this->directory, 0700);

    $sourceDatabase = 'medloby_backup_source_'.bin2hex(random_bytes(8));
    $this->postgres->statement('CREATE DATABASE "'.$sourceDatabase.'"');
    $this->createdDatabases[] = $sourceDatabase;

    config([
        'database.default' => 'backup_source',
        'database.connections.backup_source' => [...$this->postgres->getConfig(), 'name' => 'backup_source', 'url' => null, 'database' => $sourceDatabase],
        'filesystems.disks.private.root' => $this->directory.'/private',
        'filesystems.disks.local.root' => $this->directory.'/private',
    ]);

    $this->artisan('migrate')->assertSuccessful();
});

afterEach(function () {
    if (! isset($this->postgres)) {
        return;
    }

    DB::purge('backup_source');
    DB::purge('backup_restored');
    Storage::forgetDisk(['private', 'local']);

    try {
        foreach ($this->createdDatabases as $database) {
            $this->postgres->statement('DROP DATABASE "'.$database.'"');
        }
    } finally {
        File::deleteDirectory($this->directory);
    }
});

function createPostgresBackupAttachment(User $patient): MessageAttachment
{
    $branch = Branch::factory()->create();
    $profile = $patient->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
    $conversation = Conversation::create([
        'business_id' => $branch->business_id, 'branch_id' => $branch->id,
        'patient_profile_id' => $profile->id, 'subject' => 'PostgreSQL yedek provası', 'status' => 'open',
    ]);
    $message = $conversation->messages()->create([
        'sender_user_id' => $patient->id, 'body' => 'Örnek yedek mesajı', 'message_type' => 'file',
    ]);

    return $message->attachments()->create([
        'disk' => 'private', 'path' => 'conversations/'.$conversation->id.'/sample.txt',
        'original_name' => 'sample.txt', 'mime_type' => 'text/plain', 'size' => 5,
    ]);
}

test('a PostgreSQL backup restores the database and private attachment to a separate test installation', function () {
    $patient = User::factory()->create(['name' => 'Örnek hasta']);
    $attachment = createPostgresBackupAttachment($patient);
    Storage::disk('private')->put($attachment->path, 'Yedek dosya');
    $backup = $this->directory.'/backup';
    $restored = $this->directory.'/restored';
    $destinationDatabase = 'medloby_backup_restore_'.bin2hex(random_bytes(8));
    $this->postgres->statement('CREATE DATABASE "'.$destinationDatabase.'"');
    $this->createdDatabases[] = $destinationDatabase;

    $this->artisan('medloby:backup', ['destination' => $backup])->assertSuccessful();
    expect(fileperms($backup) & 0077)->toBe(0);
    $patient->update(['name' => 'Kaynakta değişen ad']);
    Storage::disk('private')->put($attachment->path, 'Kaynakta değişen dosya');
    $this->artisan('medloby:restore', ['backup' => $backup, 'destination' => $restored])->assertSuccessful();

    $config = $this->postgres->getConfig();
    Process::env([
        'PGHOST' => $config['host'], 'PGPORT' => (string) $config['port'],
        'PGUSER' => $config['username'], 'PGPASSWORD' => (string) $config['password'],
        'PGDATABASE' => $destinationDatabase,
    ])->run([
        'pg_restore', '--exit-on-error', '--single-transaction', '--no-owner', '--no-privileges',
        '--no-password', '--dbname='.$destinationDatabase, $restored.'/database.dump',
    ])->throw();

    $this->assertDatabaseHas('users', ['id' => $patient->id, 'name' => 'Kaynakta değişen ad']);
    config([
        'database.default' => 'backup_restored',
        'database.connections.backup_restored' => [...$config, 'name' => 'backup_restored', 'url' => null, 'database' => $destinationDatabase],
        'filesystems.disks.private.root' => $restored.'/storage/app/private',
    ]);
    Storage::forgetDisk('private');

    $this->assertDatabaseHas('users', ['id' => $patient->id, 'name' => 'Örnek hasta']);
    expect(DB::table('migrations')->count())->toBe(DB::connection('backup_source')->table('migrations')->count());

    $lastMigration = DB::table('migrations')->latest('id')->value('migration');
    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
    $this->assertDatabaseMissing('migrations', ['migration' => $lastMigration]);
    $this->artisan('migrate')->assertSuccessful();
    $this->assertDatabaseHas('migrations', ['migration' => $lastMigration]);

    $url = '/api/attachments/'.$attachment->id.'/view';
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertForbidden();
    $download = $this->actingAs(User::findOrFail($patient->id), 'sanctum')->get($url)->assertOk();
    expect($download->streamedContent())->toBe('Yedek dosya');
});

test('a PostgreSQL backup with a missing attachment is rejected and leaves no partial bundle', function () {
    createPostgresBackupAttachment(User::factory()->create());
    $backup = $this->directory.'/backup';

    $this->artisan('medloby:backup', ['destination' => $backup])
        ->expectsOutput('Yedekte veritabanına bağlı bir görüşme dosyası eksik.')->assertFailed();

    expect(File::exists($backup))->toBeFalse();
});

test('a damaged PostgreSQL dump with a matching checksum is rejected before import', function () {
    $backup = $this->directory.'/backup';
    $this->artisan('medloby:backup', ['destination' => $backup])->assertSuccessful();
    File::put($backup.'/database.dump', 'Geçersiz PostgreSQL yedeği');
    $manifest = json_decode(File::get($backup.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest['sha256']['database.dump'] = hash_file('sha256', $backup.'/database.dump');
    File::put($backup.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $restored = $this->directory.'/restored';

    $this->artisan('medloby:restore', ['backup' => $backup, 'destination' => $restored])
        ->expectsOutput('PostgreSQL yedek dosyası okunamadı. pg_restore sürümünü ve yedeğin bütünlüğünü kontrol edin.')->assertFailed();

    expect(File::exists($restored))->toBeFalse();
});
