<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\User;
use App\Notifications\NotificationType;
use App\Services\PlatformOperationActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

test('the scheduler delivers due reminders and expires unanswered offers across consecutive runs', function () {
    $directory = sys_get_temp_dir().'/medloby-reminder-schedule-'.Str::uuid();
    File::makeDirectory($directory, 0700);

    try {
        $database = $directory.'/schedule.sqlite';
        File::put($database, '');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => $database,
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate')->assertSuccessful();

        $branch = Branch::factory()->create();
        $staff = User::factory()->unverified()->create([
            'email' => null, 'login_code' => 'takip-personeli', 'staff_business_id' => $branch->business_id,
        ]);
        $membership = $branch->business->memberships()->create([
            'user_id' => $staff->id, 'role' => 'staff', 'is_active' => true,
        ]);
        $membership->branches()->attach($branch->id);
        $lead = CrmLead::create([
            'business_id' => $branch->business_id, 'branch_id' => $branch->id,
            'first_name' => 'Deneme Hasta Adayı', 'status' => 'new',
        ]);
        $followUp = CrmFollowUp::create([
            'business_id' => $branch->business_id, 'branch_id' => $branch->id, 'crm_lead_id' => $lead->id,
            'assigned_business_user_id' => $membership->id, 'title' => 'Geri arama',
            'scheduled_at' => now()->subHour(), 'type' => 'call', 'status' => 'pending',
        ]);

        $patientUser = User::factory()->create();
        $patient = $patientUser->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
        $startsAt = Appointment::clinicLocalNow()->addHours(4);
        $appointment = Appointment::create([
            'business_id' => $branch->business_id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $startsAt->copy()->addMinutes(30)->toDateTimeString(),
            'status' => 'confirmed',
        ]);
        $conversation = $patient->conversations()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id]);
        $offer = $conversation->offers()->create([
            'business_id' => $branch->business_id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
            'created_by' => $staff->id, 'title' => 'Bekleyen teklif', 'amount' => 500,
            'valid_until' => now()->subMinute(), 'status' => 'pending',
        ]);

        $scheduler = Process::path(base_path())->timeout(15)->env([
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => $directory.'/config.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'CACHE_STORE' => 'database',
            'DB_CACHE_CONNECTION' => 'sqlite',
            'DB_CACHE_LOCK_CONNECTION' => 'sqlite',
            'DB_CACHE_TABLE' => 'cache',
            'DB_CACHE_LOCK_TABLE' => 'cache_locks',
            'LOG_CHANNEL' => 'null',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ])->command([PHP_BINARY, 'artisan', 'schedule:run', '--no-interaction']);

        $scheduler->run()->throw();
        expect(app(PlatformOperationActivity::class)->lastObserved()['scheduler'])->not->toBeNull();
        $this->assertDatabaseCount('notifications', 2);
        expect($followUp->fresh()->reminded_at)->not->toBeNull()
            ->and($appointment->fresh()->reminded_at)->not->toBeNull();
        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => 'expired', 'responded_at' => null]);

        $scheduler->run()->throw();
        $this->assertDatabaseCount('notifications', 2);

        $followUpNotice = $this->actingAs($staff, 'sanctum')->getJson('/api/notifications/unread')
            ->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.data.notification_type', 'crm_follow_up_due')->json('data.data.0.data');
        $this->getJson("/api/branches/{$followUpNotice['branch_id']}/crm/follow-ups/{$followUpNotice['crm_follow_up_id']}")
            ->assertOk()->assertJsonPath('data.crm_lead_id', $lead->id);

        $appointmentId = $this->actingAs($patientUser, 'sanctum')->getJson('/api/notifications/unread')
            ->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.data.notification_type', NotificationType::APPOINTMENT_REMINDER)
            ->json('data.data.0.data.appointment_id');
        $this->getJson('/api/patient/appointments/'.$appointmentId)->assertOk()->assertJsonPath('data.id', $appointment->id);
    } finally {
        DB::purge('sqlite');
        File::deleteDirectory($directory);
    }
});
