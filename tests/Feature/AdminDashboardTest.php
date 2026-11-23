<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('the public admin page only offers sign in and both private routes require a verified platform administrator', function () {
    $this->withoutVite()->get('/yonetim')->assertOk()->assertSee('Yönetici e-postası')
        ->assertDontSee('Dikkat gerektiren işlemler')->assertDontSee('data-admin-dashboard-results', false);
    foreach (['dashboard', 'workspace'] as $endpoint) {
        $this->getJson('/api/admin/'.$endpoint)->assertUnauthorized();
    }

    $branch = Branch::factory()->create();
    $staff = User::factory()->unverified()->create(['staff_business_id' => $branch->business_id]);
    $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true])
        ->branches()->attach($branch);
    $patient = User::factory()->create();
    $patient->patientProfile()->create(['first_name' => 'Örnek', 'last_name' => 'Hasta']);
    $unverifiedAdmin = User::factory()->unverified()->create(['is_admin' => true]);
    foreach ([$patient, $staff, $unverifiedAdmin] as $account) {
        $this->actingAs($account, 'sanctum');
        foreach (['dashboard', 'workspace'] as $endpoint) {
            $this->getJson('/api/admin/'.$endpoint)->assertForbidden();
        }
    }

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/workspace')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Klinik başvuruları')->assertSee('Üyelik sözleşmeleri')->assertSee('Destek talepleri');
});

test('only verified administrators receive tokens through the admin login without affecting other accounts', function () {
    $patient = User::factory()->create();
    $oldToken = $patient->createToken('patient');
    $this->postJson('/api/admin/login', ['email' => $patient->email, 'password' => 'password', 'is_admin' => true])
        ->assertForbidden()->assertJsonMissingPath('data.token');
    expect($patient->fresh()->is_admin)->toBeFalse();
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $oldToken->accessToken->id]);

    $admin = User::factory()->create(['is_admin' => true]);
    $login = $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.user.id', $admin->id)->assertJsonMissingPath('data.user.email');
    $token = $login->json('data.token');
    $this->withToken($token)->getJson('/api/admin/dashboard')->assertOk();

    $admin->forceFill(['is_admin' => false])->save();
    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
});

test('dashboard counts use Istanbul calendar boundaries and report aggregates without private data', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-06 21:30:00', 'UTC'));
    $owner = User::factory()->create(['name' => 'Gizli Klinik Sahibi', 'created_at' => '2026-12-06 20:59:59']);
    $patient = User::factory()->create(['email' => 'private-patient@example.test', 'created_at' => '2026-12-06 21:00:00']);
    $profile = $patient->patientProfile()->create([
        'first_name' => 'Özel Hasta', 'last_name' => 'Test', 'phone' => '5558134567',
    ]);
    $admin = User::factory()->create(['is_admin' => true, 'created_at' => '2026-11-30 20:00:00']);

    $clinic = Business::factory()->create([
        'membership_plan' => 'standard', 'is_verified' => true, 'status' => 'active', 'created_at' => '2026-12-06 20:59:59',
    ]);
    Business::factory()->create(['status' => 'pending', 'membership_plan' => 'starter', 'created_at' => '2026-12-06 21:00:00']);
    $suspended = Business::factory()->create(['status' => 'suspended', 'membership_plan' => null, 'created_at' => '2026-10-12 00:00:00']);
    Business::factory()->create(['type' => 'other', 'created_at' => '2026-12-06 21:00:00']);
    $branch = Branch::factory()->for($clinic)->create(['status' => 'active']);
    Branch::factory()->for($suspended)->create(['status' => 'active']);
    Branch::factory()->for($clinic)->create(['status' => 'inactive']);

    foreach ([
        ['2026-12-06 23:59:00', 'completed'], ['2026-12-07 00:00:00', 'confirmed'],
        ['2026-12-07 09:00:00', 'cancelled'], ['2026-12-07 10:00:00', 'rescheduled'],
        ['2026-12-08 08:00:00', 'pending'], ['2026-12-14 00:00:00', 'pending'],
    ] as [$start, $value]) {
        $branch->appointments()->create([
            'business_id' => $clinic->id, 'patient_profile_id' => $profile->id, 'starts_at' => $start,
            'ends_at' => CarbonImmutable::parse($start)->addMinutes(30), 'status' => $value, 'notes' => 'Gizli randevu notu',
        ]);
    }

    $conversation = $profile->conversations()->create(['business_id' => $clinic->id, 'branch_id' => $branch->id]);
    foreach (['2026-12-06 21:29:59', '2026-12-06 21:30:00', null] as $deadline) {
        $conversation->offers()->create([
            'business_id' => $clinic->id, 'branch_id' => $branch->id, 'patient_profile_id' => $profile->id,
            'created_by' => $owner->id, 'status' => 'pending', 'title' => 'Gizli teklif', 'amount' => '75123.44',
            'valid_until' => $deadline,
        ]);
    }
    SupportTicket::factory()->for($patient, 'requester')->create(['subject' => 'Gizli destek konusu']);
    SupportTicket::factory()->for($owner, 'requester')->create(['closed_at' => now()]);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => 'Gizli iş yükü', 'exception' => 'Gizli teknik hata',
    ]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.as_of', '2026-12-07T00:30:00+03:00')
        ->assertJsonPath('data.summary', [
            'clinics' => 3, 'active_clinics' => 1, 'suspended_clinics' => 1, 'pending_applications' => 1, 'patient_accounts' => 1,
            'new_users' => ['today' => 1, 'month' => 2], 'appointments' => ['today' => 2, 'week' => 3, 'month' => 5],
            'pending_offers' => 2, 'open_tickets' => 1, 'active_branches' => 1,
        ])
        ->assertJsonPath('data.trends.clinics.28', ['date' => '2026-12-06', 'count' => 1])
        ->assertJsonPath('data.trends.clinics.29', ['date' => '2026-12-07', 'count' => 1])
        ->assertJsonCount(30, 'data.trends.appointments')
        ->assertJsonPath('data.trends.appointments.29', ['date' => '2026-12-07', 'count' => 2])
        ->assertJsonPath('data.appointment_statuses.0.count', 0)
        ->assertJsonPath('data.appointment_statuses.3.count', 1)
        ->assertJsonPath('data.plans.1', ['key' => 'standard', 'label' => 'Standart', 'count' => 1])
        ->assertJsonPath('data.plans.3.count', 1)
        ->assertJsonPath('data.support.1.count', 1)
        ->assertJsonPath('data.alerts', ['membership_contract_missing' => true, 'offers_awaiting_expiry' => 1, 'failed_jobs' => 1])
        ->assertDontSee('Gizli')->assertDontSee('private-patient@example.test')
        ->assertDontSee('5558134567')->assertDontSee('75123.44');
});

test('the dashboard distinguishes empty totals from an unknown failed job count', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    config(['queue.failed.driver' => null]);
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard')->assertOk()
        ->assertJsonPath('data.summary.clinics', 0)->assertJsonPath('data.summary.patient_accounts', 0)
        ->assertJsonPath('data.summary.appointments.today', 0)->assertJsonPath('data.alerts.failed_jobs', null)
        ->assertJsonCount(30, 'data.trends.clinics');
});
