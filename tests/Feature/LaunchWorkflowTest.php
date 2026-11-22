<?php

use App\Models\PlatformContract;
use App\Models\User;
use App\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('a newly approved clinic and a verified patient can complete a private package reservation', function () {
    config(['mail.default' => 'array', 'queue.default' => 'database']);
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    $administrator = User::factory()->create(['is_admin' => true]);
    $unverifiedAdministrator = User::factory()->unverified()->create(['is_admin' => true]);
    $otherUser = User::factory()->create();
    $administratorToken = $administrator->createToken('launch-test')->plainTextToken;
    PlatformContract::create([
        'contract_type' => 'clinic_membership', 'title' => 'Prova sözleşmesi', 'version' => 'pilot',
        'content' => 'Sentetik klinik üyelik sözleşmesi.', 'status' => 'published', 'is_required' => true,
        'published_at' => now(), 'effective_at' => now(),
    ]);

    $authenticate = function (string $token): void {
        Auth::forgetGuards();
        $this->withToken($token);
    };
    $drainQueue = function (): void {
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    };
    $verifyLatestEmail = function (string $recipient): void {
        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        expect($mail->getTo()[0]->getAddress())->toBe($recipient);
        expect(preg_match('~href="([^"]*/email/verify/[^"]+)"~', $mail->getHtmlBody(), $match))->toBe(1);
        $this->getJson(html_entity_decode($match[1]))->assertOk();
    };

    $agreement = $this->getJson('/api/register/business/contract')->assertOk()->json('data.id');
    $ownerEmail = 'klinik-prova@example.test';
    $password = 'local-pilot-pass';
    $this->postJson('/api/register/business/application', [
        'owner_email' => $ownerEmail, 'business_name' => 'Prova Sağlık Merkezi', 'business_phone' => '+90 312 555 01 11',
        'membership_plan' => 'standard', 'platform_contract_id' => $agreement, 'contract_accepted' => true,
    ])->assertCreated()->assertJsonPath('data.approval_status', 'pending')->assertJsonMissingPath('data.token');
    $owner = User::query()->where('email', $ownerEmail)->sole();
    $clinic = $owner->businessMemberships()->sole()->business;
    $business = $clinic->id;
    $branch = $clinic->branches()->sole()->id;
    $crm = "/api/businesses/{$business}/crm";
    $this->getJson($crm)->assertUnauthorized();
    $this->postJson('/api/login', ['identifier' => $ownerEmail, 'password' => $password])->assertUnprocessable();
    expect($owner->tokens()->count())->toBe(0);
    $this->assertDatabaseCount('jobs', 0);

    $authenticate($administratorToken);
    $submitted = $this->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.data.notification_type', NotificationType::CLINIC_APPLICATION_SUBMITTED)
        ->assertJsonPath('data.data.0.data.message', 'Prova Sağlık Merkezi başvurusu değerlendirme bekliyor.')
        ->assertJsonPath('data.data.0.data.clinic_application_id', $business)->json('data.data.0.id');
    expect($unverifiedAdministrator->notifications()->count())->toBe(0);
    expect($otherUser->notifications()->count())->toBe(0);
    $this->postJson('/api/notifications/'.$submitted.'/read')->assertOk();
    $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 0);
    $this->getJson('/api/admin/businesses/directory?status=pending')->assertOk()
        ->assertJsonPath('data.data.0.membership_plan', 'Standart');
    $this->postJson("/api/admin/businesses/{$business}/approve")->assertOk()
        ->assertJsonPath('data.business.branches.0.status', 'active');
    $this->postJson("/api/admin/businesses/{$business}/approve")->assertUnprocessable();
    $category = $this->postJson('/api/treatment-categories', ['name' => 'Prova kategorisi', 'is_active' => true])
        ->assertCreated()->json('data.id');
    $treatment = $this->postJson('/api/treatments', [
        'treatment_category_id' => $category, 'name' => 'Prova kontrolü', 'duration_minutes' => 30, 'is_active' => true,
    ])->assertCreated()->json('data.id');

    $drainQueue();
    $credentials = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($credentials->getTo()[0]->getAddress())->toBe($ownerEmail);
    expect(preg_match('/Geçici şifreniz:\s+([A-Za-z0-9]{24})/u', $credentials->getTextBody(), $temporary))->toBe(1);
    Auth::forgetGuards();
    $this->withoutToken();
    $login = ['identifier' => $owner->fresh()->login_code, 'password' => $temporary[1]];
    $this->postJson('/api/login', $login)->assertOk()
        ->assertJsonPath('data.password_change_required', true)->assertJsonMissingPath('data.token');
    $ownerToken = $this->postJson('/api/login', $login + ['new_password' => $password, 'new_password_confirmation' => $password])
        ->assertOk()->json('data.token');

    $authenticate($ownerToken);
    $this->getJson('/api/notifications/unread')->assertOk()->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.data.notification_type', NotificationType::CLINIC_APPLICATION_APPROVED)
        ->assertJsonPath('data.data.0.data.clinic_application_id', $business);
    $this->getJson('/api/clinic-applications')->assertOk()->assertJsonPath('data.0.crm_available', true);
    $this->getJson($crm)->assertOk()->assertJsonPath('data.branches.0.id', $branch)->assertJsonPath('data.membership_plan', 'Standart');
    $this->putJson($crm.'/profile', [
        'branch_id' => $branch, 'owner_name' => 'Prova İşletmecisi',
        'clinic' => ['name' => 'Prova Sağlık Merkezi', 'country_code' => 'TR', 'city' => 'Ankara'],
        'branch' => ['name' => 'Merkez Şube', 'country_code' => 'TR', 'address' => 'Test adresi'],
    ])->assertOk();
    $otherBranch = $this->postJson($crm.'/branches', ['name' => 'İkinci Şube'])->assertCreated()->json('data.id');
    $operations = "/api/branches/{$branch}/operations";
    $calendar = "/api/branches/{$branch}/clinic-calendar";
    $staffAccount = ['name' => 'Prova Personeli', 'login_code' => 'prova-personeli', 'password' => 'local-staff-pass'];
    $this->postJson($operations.'/staff/accounts', $staffAccount)->assertCreated();
    $staff = User::query()->where('login_code', $staffAccount['login_code'])->sole();
    $staffMembership = $staff->businessMemberships()->sole()->id;
    $this->putJson($operations."/staff/{$staffMembership}/permissions", [
        'permissions' => ['appointments.confirm', 'travel.manage', 'finance.view'],
    ])->assertOk();
    $ledger = "/api/branches/{$branch}/crm/ledger";
    $entry = [
        'kind' => 'expense', 'entry_date' => '2026-12-01', 'amount' => '35.20', 'currency' => 'TRY',
        'description' => 'Prova gideri', 'request_key' => str_repeat('a', 32),
    ];
    $this->postJson($ledger, $entry)->assertCreated();

    Auth::forgetGuards();
    $staffToken = $this->withoutToken()->postJson('/api/login', [
        'identifier' => $staffAccount['login_code'], 'password' => $staffAccount['password'],
    ])->assertOk()->json('data.token');
    $authenticate($staffToken);
    $this->getJson($crm)->assertOk()->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branches.0.id', $branch);
    $this->getJson("/api/branches/{$otherBranch}/crm/dashboard")->assertForbidden();
    $this->getJson($ledger.'?month=2026-12')->assertOk()->assertJsonPath('data.can_manage', false)->assertJsonPath('data.totals.0.expense', '35.20');
    $this->postJson($ledger, $entry)->assertForbidden();

    $authenticate($ownerToken);
    $doctor = $this->postJson($operations.'/doctors', ['first_name' => 'Prova', 'last_name' => 'Hekimi'])
        ->assertCreated()->json('data.id');
    $this->postJson($operations.'/treatments', ['treatment_id' => $treatment, 'is_online_bookable' => true])->assertCreated();
    $this->putJson($operations."/doctors/{$doctor}/treatments/{$treatment}", ['is_active' => true])->assertOk();
    $this->postJson($calendar."/doctors/{$doctor}/working-hours", [
        'day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '12:00',
    ])->assertCreated();

    $package = $this->postJson('/api/treatment-packages', [
        'business_id' => $business, 'branch_id' => $branch, 'name' => 'İki seanslık prova', 'price' => 500,
        'currency' => 'TRY', 'is_active' => true, 'is_offer_enabled' => true,
        'includes_transfer' => true,
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/treatment-packages/{$package}/items", ['treatment_id' => $treatment, 'quantity' => 2])->assertCreated();
    Auth::forgetGuards();
    $this->withoutToken()->getJson('/api/package-catalog')->assertOk()
        ->assertJsonPath('data.data.0.id', $package)->assertJsonMissingPath('data.data.0.price');

    $patientEmail = 'hasta-prova@example.test';
    $patient = $this->postJson('/api/register/patient', [
        'first_name' => 'Deneme', 'last_name' => 'Hasta', 'email' => $patientEmail,
        'password' => $password, 'password_confirmation' => $password,
    ])->assertCreated()->json('data');
    $patientToken = $patient['token'];
    $authenticate($patientToken);
    $this->postJson('/api/conversations', ['treatment_package_id' => $package])->assertForbidden();
    $drainQueue();
    $verifyLatestEmail($patientEmail);
    $authenticate($patientToken);
    $conversation = $this->postJson('/api/conversations', ['treatment_package_id' => $package])
        ->assertCreated()->json('data.id');

    $authenticate($ownerToken);
    $offer = $this->postJson("/api/conversations/{$conversation}/offers", [
        'treatment_package_id' => $package, 'title' => 'Hastaya özel iki seans', 'amount' => 450, 'currency' => 'TRY',
    ])->assertCreated()->assertJsonPath('data.package_snapshot.treatments.0.quantity', 2)
        ->assertJsonPath('data.package_snapshot.includes_transfer', true)->json('data.id');

    $drainQueue();
    $authenticate($patientToken);
    $this->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonFragment(['type' => NotificationType::NEW_OFFER, 'offer_id' => $offer]);
    $this->postJson("/api/offers/{$offer}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
    $appointmentPath = "/api/offers/{$offer}/appointments";
    $options = $appointmentPath."/options?branch_id={$branch}&treatment_id={$treatment}&date=2026-12-04";
    $onlineBooking = $operations."/doctors/{$doctor}/online-booking";
    $this->getJson($options)->assertOk()->assertJsonPath('data.doctors', []);
    $this->putJson($onlineBooking, ['is_public' => true])->assertForbidden();

    $drainQueue();
    $authenticate($ownerToken);
    $this->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonFragment(['notification_type' => NotificationType::OFFER_ACCEPTED, 'offer_id' => $offer]);
    $this->putJson($onlineBooking, ['is_public' => true])->assertOk();

    $route = ['starts_at' => '2026-12-04T09:15', 'from' => 'Örnek terminal', 'to' => 'Merkez Şube'];
    $travel = "/api/offers/{$offer}/travel-plan";
    $authenticate($staffToken);
    $this->putJson($travel, ['revision' => 0, 'transfers' => [$route]])->assertOk();

    $authenticate($patientToken);
    $this->getJson($travel)->assertOk()->assertJsonPath('data.transfers.0', $route)->assertJsonPath('data.can_manage', false);
    $this->getJson($ledger)->assertForbidden();
    $slots = $this->getJson($options)->assertOk()->assertJsonPath('data.doctors.0.id', $doctor)->json('data.doctors.0.slots');
    expect($slots)->toContain('2026-12-04 10:00:00', '2026-12-04 11:00:00');
    $visitNotes = 'Randevu öncesinde danışmaya uğrayacağım.';
    $bookings = $this->postJson($appointmentPath, [
        'branch_id' => $branch,
        'sessions' => [
            ['doctor_id' => $doctor, 'treatment_id' => $treatment, 'starts_at' => '2026-12-04 10:00:00', 'notes' => $visitNotes],
            ['doctor_id' => $doctor, 'treatment_id' => $treatment, 'starts_at' => '2026-12-04 11:00:00'],
        ],
    ])->assertCreated()->assertJsonCount(2, 'data.appointments')->json('data.appointments');

    $authenticate($staffToken);
    $this->getJson($calendar.'?date=2026-12-04')->assertOk()->assertJsonCount(2, 'data.appointments')
        ->assertJsonPath('data.appointments.0.id', $bookings[0]['id']);
    $this->postJson($calendar.'/appointments/'.$bookings[0]['id'].'/confirm')
        ->assertOk()->assertJsonPath('data.status', 'confirmed');
    $this->getJson($calendar.'/appointments/'.$bookings[0]['id'])->assertOk()
        ->assertJsonPath('data.notes', $visitNotes)->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.email', $patientEmail);
    $this->getJson($calendar.'/appointments/'.$bookings[1]['id'])->assertOk()->assertJsonPath('data.notes', null);
    $authenticate($ownerToken);
    $this->putJson($onlineBooking, ['is_public' => false])->assertOk();

    $drainQueue();
    $authenticate($patientToken);
    $this->getJson($appointmentPath)->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'confirmed')->assertJsonPath('data.1.status', 'pending');
    $this->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonFragment(['notification_type' => NotificationType::APPOINTMENT_CONFIRMED, 'appointment_id' => $bookings[0]['id']])
        ->assertJsonFragment(['notification_type' => NotificationType::OFFER_TRAVEL_PLAN_UPDATED, 'offer_id' => $offer]);
    $events = $this->getJson('/api/patient/journey')->assertOk()->json('data.events');
    expect(array_column($events, 'kind'))->toContain('conversation', 'offer_received', 'offer_responded', 'appointment_status');
});
