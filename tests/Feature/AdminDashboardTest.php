<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\PlatformContract;
use App\Models\SupportTicket;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Services\PlatformOperationActivity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('the public admin page only offers sign in and both private routes require a verified platform administrator', function () {
    $this->withoutVite()->get('/yonetim')->assertOk()->assertSee('Yönetici e-postası')
        ->assertDontSee('Dikkat gerektiren işlemler')->assertDontSee('data-admin-dashboard-results', false);
    foreach (['dashboard', 'workspace', 'analytics', 'patient-accounts', 'operations', 'audit'] as $endpoint) {
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
        foreach (['dashboard', 'workspace', 'analytics', 'patient-accounts', 'operations', 'audit'] as $endpoint) {
            $this->getJson('/api/admin/'.$endpoint)->assertForbidden();
        }
    }

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/workspace')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Klinik başvuruları')->assertSee('Üyelik sözleşmeleri')->assertSee('Destek talepleri');
});

test('platform operations are presented only in the admin workspace', function () {
    $this->withoutVite()->get('/paket-teklifleri')->assertOk()
        ->assertSee('id="platform-admin-link"', false)
        ->assertDontSee('id="clinic-review-dialog"', false)
        ->assertDontSee('id="clinic-administration-dialog"', false)
        ->assertDontSee('id="clinic-contracts-dialog"', false)
        ->assertDontSee('id="treatment-administration-dialog"', false)
        ->assertDontSee('id="shared-package-services-button"', false)
        ->assertSee('id="package-service-dialog"', false)
        ->assertSee('id="support-tickets-dialog"', false);
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
    $ticket = SupportTicket::factory()->for($patient, 'requester')->create(['subject' => 'Gizli destek konusu']);
    $ticket->messages()->create(['from_support' => false, 'body' => 'Gizli destek mesajı']);
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
            'pending_offers' => 2, 'open_tickets' => 1, 'awaiting_support_reply' => 1, 'active_branches' => 1,
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
        ->assertJsonPath('data.alerts', [
            'membership_contract_missing' => true, 'membership_contract_expires_at' => null,
            'offers_awaiting_expiry' => 1, 'failed_jobs' => 1, 'active_treatments_in_inactive_categories' => 0,
        ])
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

test('admin offer analytics count a converted offer once across multiple valid appointments', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-06 21:30:00', 'UTC'));
    $admin = User::factory()->create(['is_admin' => true]);
    $branch = Branch::factory()->for(Business::factory()->state(['city' => 'Ankara']))->create();
    $otherBranch = Branch::factory()->for(Business::factory()->state(['city' => null]))->create();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Gizli', 'last_name' => 'Kişi']);
    $conversation = $patient->conversations()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id]);
    $offer = fn (string $status, string $created) => $conversation->offers()->forceCreate([
        'business_id' => $branch->business_id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $admin->id, 'status' => $status, 'title' => 'Gizli Teklif', 'amount' => '1230.25', 'created_at' => $created,
    ]);
    $converted = $offer('accepted', '2026-11-07 21:00:00');
    $wrongBusiness = $offer('accepted', '2026-12-06 21:00:00');
    $offer('expired', '2026-12-06 21:15:00');
    $offer('pending', '2026-11-07 20:59:59');
    foreach ([[$branch, $converted->id], [$branch, $converted->id], [$otherBranch, $wrongBusiness->id]] as $index => [$location, $offerId]) {
        $start = CarbonImmutable::parse('2026-12-07 09:30:00')->addHours($index);
        $location->appointments()->create([
            'business_id' => $location->business_id, 'patient_profile_id' => $patient->id, 'offer_id' => $offerId,
            'status' => 'confirmed', 'starts_at' => $start, 'ends_at' => $start->addMinutes(30),
        ]);
    }

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/analytics')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.starts_on', '2026-11-08')->assertJsonPath('data.ends_on', '2026-12-07')
        ->assertJsonPath('data.offers_created', 3)->assertJsonPath('data.offers_converted', 1)
        ->assertJsonPath('data.clinics_with_appointments', 2)->assertJsonPath('data.outcomes.1.count', 2)
        ->assertJsonFragment(['label' => 'Ankara · TR', 'count' => 1])
        ->assertJsonFragment(['label' => 'İl belirtilmemiş · TR', 'count' => 1])
        ->assertDontSee('Gizli')->assertDontSee('1230.25');
});

test('the dashboard reports only configured operational observations and catalog and contract indicators', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    config([
        'medloby.release_version' => '2991bb1',
        'queue.default' => 'database', 'queue.connections.database.connection' => null,
        'mail.default' => 'log', 'mail.mailers.smtp.password' => 'Loga girmemeli',
    ]);
    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership', 'title' => 'Gizli sözleşme başlığı', 'version' => 'test',
        'content' => 'Gizli sözleşme metni', 'status' => 'published', 'is_required' => true,
        'published_at' => now()->subDay(), 'expires_at' => now()->addDays(5),
    ]);
    $category = TreatmentCategory::factory()->create(['is_active' => false]);
    Treatment::create(['treatment_category_id' => $category->id, 'name' => 'Örnek', 'slug' => 'ornek', 'is_active' => true]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/operations')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.application', 'reachable')->assertJsonPath('data.database', 'ready')
        ->assertJsonPath('data.migrations', 'ready')->assertJsonPath('data.queue_configuration', 'ready')
        ->assertJsonPath('data.queue_worker', 'unknown')->assertJsonPath('data.scheduler', 'unknown')
        ->assertJsonPath('data.smtp_configuration', 'not_configured')->assertJsonPath('data.email_delivery', 'unknown')
        ->assertJsonPath('data.backup_verification', 'unknown')->assertJsonPath('data.release_version', '2991bb1')
        ->assertDontSee('Loga girmemeli');
    $this->getJson('/api/admin/dashboard')->assertOk()
        ->assertJsonPath('data.alerts.membership_contract_missing', false)
        ->assertJsonPath('data.alerts.membership_contract_expires_at', $contract->fresh()->expires_at->toIso8601String())
        ->assertJsonPath('data.alerts.active_treatments_in_inactive_categories', 1)
        ->assertDontSee('Gizli sözleşme');
});

test('operation observations contain only timestamps and are recorded from successful SMTP sends', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $activity = app(PlatformOperationActivity::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-23T16:45:00Z'));

    event(new MessageSent(Mockery::mock(SentMessage::class), ['mailer' => 'log']));
    expect($activity->lastObserved()['email_delivery'])->toBeNull();
    event(new MessageSent(Mockery::mock(SentMessage::class), [
        'mailer' => 'smtp', 'recipient' => 'never-displayed@example.test',
    ]));
    $activity->record(PlatformOperationActivity::BACKUP);
    $this->travel(-1)->minutes();
    $activity->record(PlatformOperationActivity::BACKUP);

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/operations')->assertOk()
        ->assertJsonPath('data.email_delivery', 'observed')
        ->assertJsonPath('data.backup_verification', 'observed')
        ->assertJsonPath('data.last_observed_at', [
            'queue_worker' => null, 'scheduler' => null,
            'email_delivery' => '2026-11-23T16:45:00+00:00', 'backup_verification' => '2026-11-23T16:45:00+00:00',
        ])->assertDontSee('never-displayed@example.test');
});

test('the audit groups existing administrator actions without revealing messages or internal reasons', function () {
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'İnceleyen Yönetici']);
    $patient = User::factory()->create(['name' => 'Gizli Hasta']);
    $clinic = Business::factory()->create(['name' => 'Örnek Klinik']);
    $clinic->reviews()->create([
        'reviewed_by_user_id' => $admin->id, 'decision' => 'approved', 'reviewed_at' => '2026-11-23 08:00:00',
        'notes' => 'Gizli inceleme notu',
    ]);
    $clinic->statusHistories()->create([
        'changed_by_user_id' => $admin->id, 'previous_status' => 'active', 'new_status' => 'suspended',
        'reason' => 'Gizli askıya alma sebebi', 'changed_at' => '2026-11-23 10:00:00',
    ]);
    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership', 'title' => 'Üyelik', 'version' => '2026',
        'content' => 'Gizli sözleşme metni', 'status' => 'published', 'is_required' => true,
        'published_at' => '2026-11-23 09:00:00', 'updated_by_user_id' => $admin->id,
    ]);
    $ticket = SupportTicket::factory()->for($patient, 'requester')->create(['subject' => 'Gizli Destek Konusu']);
    $ticket->messages()->forceCreate([
        'from_support' => false, 'sender_user_id' => $patient->id, 'body' => 'Gizli hasta mesajı', 'created_at' => '2026-11-23 11:00:00',
    ]);
    $ticket->messages()->forceCreate([
        'from_support' => true, 'sender_user_id' => $admin->id, 'body' => 'Gizli destek yanıtı', 'created_at' => '2026-11-23 12:00:00',
    ]);

    $path = '/api/admin/audit';
    $this->actingAs($admin, 'sanctum')->getJson($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.total', 4)
        ->assertJsonPath('data.data.0', [
            'source' => 'support', 'action' => 'replied', 'target_id' => $ticket->id, 'target_name' => null,
            'actor_name' => $admin->name, 'occurred_at' => '2026-11-23T12:00:00+00:00',
        ])
        ->assertJsonPath('data.data.1.source', 'clinic_status')
        ->assertJsonPath('data.data.2.target_id', $contract->id)->assertJsonPath('data.data.2.actor_name', null)
        ->assertJsonPath('data.data.3.target_name', $clinic->name)->assertDontSee('Gizli');
    $this->getJson($path.'?source=clinic_application')->assertOk()->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.action', 'approved');
    $this->getJson($path.'?source=invalid')->assertUnprocessable()->assertJsonValidationErrors('source');
});
