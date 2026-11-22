<?php

use App\Models\PlatformContract;
use App\Models\User;
use App\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('a newly approved clinic and a verified patient can complete a private package reservation', function () {
    config(['mail.default' => 'array', 'queue.default' => 'sync']);
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    $administrator = User::factory()->create(['is_admin' => true]);
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
    $verifyLatestEmail = function (string $recipient): void {
        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        expect($mail->getTo()[0]->getAddress())->toBe($recipient);
        expect(preg_match('~href="([^"]*/email/verify/[^"]+)"~', $mail->getHtmlBody(), $match))->toBe(1);
        $this->getJson(html_entity_decode($match[1]))->assertOk();
    };

    $agreement = $this->getJson('/api/register/business/contract')->assertOk()->json('data.id');
    $ownerEmail = 'klinik-prova@example.test';
    $password = 'local-pilot-pass';
    $clinic = $this->postJson('/api/register/business', [
        'owner_name' => 'Prova İşletmecisi', 'owner_email' => $ownerEmail,
        'password' => $password, 'password_confirmation' => $password,
        'business_name' => 'Prova Sağlık Merkezi', 'business_email' => 'merkez-prova@example.test',
        'city' => 'Ankara', 'address' => 'Test adresi', 'platform_contract_id' => $agreement, 'contract_accepted' => true,
    ])->assertCreated()->assertJsonPath('data.approval_status', 'pending')->json('data');
    $ownerToken = $clinic['token'];
    $business = $clinic['business']['id'];
    $branch = $clinic['branch']['id'];
    $crm = "/api/businesses/{$business}/crm";

    $authenticate($ownerToken);
    $this->getJson($crm)->assertForbidden();
    $verifyLatestEmail($ownerEmail);
    $authenticate($ownerToken);
    $this->getJson('/api/clinic-applications')->assertOk()
        ->assertJsonPath('data.0.status', 'pending')->assertJsonPath('data.0.crm_available', false);
    $this->getJson($crm)->assertForbidden();

    $authenticate($administratorToken);
    $this->postJson("/api/admin/businesses/{$business}/approve")->assertOk()
        ->assertJsonPath('data.business.branches.0.status', 'active');
    $category = $this->postJson('/api/treatment-categories', ['name' => 'Prova kategorisi', 'is_active' => true])
        ->assertCreated()->json('data.id');
    $treatment = $this->postJson('/api/treatments', [
        'treatment_category_id' => $category, 'name' => 'Prova kontrolü', 'duration_minutes' => 30, 'is_active' => true,
    ])->assertCreated()->json('data.id');

    $authenticate($ownerToken);
    $this->getJson('/api/clinic-applications')->assertOk()->assertJsonPath('data.0.crm_available', true);
    $this->getJson($crm)->assertOk()->assertJsonPath('data.branches.0.id', $branch);
    $operations = "/api/branches/{$branch}/operations";
    $calendar = "/api/branches/{$branch}/clinic-calendar";
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
    $verifyLatestEmail($patientEmail);
    $authenticate($patientToken);
    $conversation = $this->postJson('/api/conversations', ['treatment_package_id' => $package])
        ->assertCreated()->json('data.id');

    $authenticate($ownerToken);
    $offer = $this->postJson("/api/conversations/{$conversation}/offers", [
        'treatment_package_id' => $package, 'title' => 'Hastaya özel iki seans', 'amount' => 450, 'currency' => 'TRY',
    ])->assertCreated()->assertJsonPath('data.package_snapshot.treatments.0.quantity', 2)->json('data.id');

    $authenticate($patientToken);
    $this->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonFragment(['type' => NotificationType::NEW_OFFER, 'offer_id' => $offer]);
    $this->postJson("/api/offers/{$offer}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
    $appointmentPath = "/api/offers/{$offer}/appointments";
    $options = $appointmentPath."/options?branch_id={$branch}&treatment_id={$treatment}&date=2026-12-04";
    $onlineBooking = $operations."/doctors/{$doctor}/online-booking";
    $this->getJson($options)->assertOk()->assertJsonPath('data.doctors', []);
    $this->putJson($onlineBooking, ['is_public' => true])->assertForbidden();

    $authenticate($ownerToken);
    $this->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonFragment(['notification_type' => NotificationType::OFFER_ACCEPTED, 'offer_id' => $offer]);
    $this->putJson($onlineBooking, ['is_public' => true])->assertOk();

    $authenticate($patientToken);
    $slots = $this->getJson($options)->assertOk()->assertJsonPath('data.doctors.0.id', $doctor)->json('data.doctors.0.slots');
    expect($slots)->toContain('2026-12-04 10:00:00', '2026-12-04 11:00:00');
    $bookings = $this->postJson($appointmentPath, [
        'branch_id' => $branch,
        'sessions' => [
            ['doctor_id' => $doctor, 'treatment_id' => $treatment, 'starts_at' => '2026-12-04 10:00:00'],
            ['doctor_id' => $doctor, 'treatment_id' => $treatment, 'starts_at' => '2026-12-04 11:00:00'],
        ],
    ])->assertCreated()->assertJsonCount(2, 'data.appointments')->json('data.appointments');

    $authenticate($ownerToken);
    $this->getJson($calendar.'?date=2026-12-04')->assertOk()->assertJsonCount(2, 'data.appointments')
        ->assertJsonPath('data.appointments.0.id', $bookings[0]['id']);
    $this->postJson($calendar.'/appointments/'.$bookings[0]['id'].'/confirm')
        ->assertOk()->assertJsonPath('data.status', 'confirmed');
    $this->putJson($onlineBooking, ['is_public' => false])->assertOk();

    $authenticate($patientToken);
    $this->getJson($appointmentPath)->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'confirmed')->assertJsonPath('data.1.status', 'pending');
    $this->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonFragment(['notification_type' => NotificationType::APPOINTMENT_CONFIRMED, 'appointment_id' => $bookings[0]['id']]);
});
