<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessNotificationPreference;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Notifications\NewOfferNotification;
use App\Notifications\NotificationType;
use App\Notifications\OfferAcceptedNotification;
use App\Services\BusinessNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createNotificationTestData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $patientUser = User::factory()->create();

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $businessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Bildirim Test Şubesi',
        'slug' => 'bildirim-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Bildirim testi',
        'status' => 'open',
    ]);

    $category = TreatmentCategory::create([
        'parent_id' => null,
        'name' => 'Bildirim Test Kategorisi',
        'slug' => 'bildirim-test-kategorisi-' . uniqid(),
        'description' => 'Bildirim sistemi test kategorisi.',
        'image' => null,
        'icon' => null,
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Bildirim Test Tedavisi',
        'slug' => 'bildirim-test-tedavisi-' . uniqid(),
        'description' => 'Bildirim sistemi test tedavisi.',
        'duration_minutes' => 60,
        'preparation' => null,
        'aftercare' => null,
        'included_services' => null,
        'excluded_services' => null,
        'image' => null,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branch->id,
        'treatment_id' => $treatment->id,
        'is_active' => true,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'duration_minutes' => 60,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'business' => $business,
        'businessUser' => $businessUser,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'branch' => $branch,
        'conversation' => $conversation,
        'treatment' => $treatment,
    ];
}

test('new offer notification can be stored in database', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $patientUser = User::factory()->create();

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Bildirim testi',
        'status' => 'open',
    ]);

    $creator = User::factory()->create();

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'treatment_id' => null,
        'created_by' => $creator->id,
        'title' => 'Bildirim test teklifi',
        'description' => 'Bildirim sistemi testi.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'pending',
    ]);

    $patientUser->notify(
        new NewOfferNotification($offer)
    );

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $patientUser->id,
        'notifiable_type' => User::class,
        'type' => NewOfferNotification::class,
    ]);
});

test('patient receives notification when business creates an offer', function () {
    $data = createNotificationTestData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            [
                'treatment_id' => $data['treatment']->id,
                'title' => 'Saç ekimi teklifi',
                'description' => 'Bildirim entegrasyonu testi.',
                'amount' => 50000,
                'currency' => 'TRY',
                'valid_until' => now()
                    ->addDays(7)
                    ->toDateTimeString(),
            ]
        );

    $response->assertCreated();

    $offerId = $response->json('data.id');

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $data['patientUser']->id,
        'notifiable_type' => User::class,
        'type' => NewOfferNotification::class,
    ]);

    $notification = DB::table('notifications')
        ->where('notifiable_id', $data['patientUser']->id)
        ->where('notifiable_type', User::class)
        ->where('type', NewOfferNotification::class)
        ->latest('created_at')
        ->first();

    expect($notification)->not->toBeNull();

    $notificationData = json_decode(
        $notification->data,
        true
    );

    expect($notificationData['offer_id'])
        ->toBe($offerId);

    expect($notificationData['conversation_id'])
        ->toBe($data['conversation']->id);

    expect($notificationData['business_id'])
        ->toBe($data['business']->id);

    expect($notificationData['branch_id'])
        ->toBe($data['branch']->id);
});

test('offer creator receives notification when patient accepts offer', function () {
    $data = createNotificationTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => $data['treatment']->id,
        'created_by' => $data['businessUser']->id,
        'title' => 'Kabul bildirimi testi',
        'description' => 'Teklif kabul bildirimi testi.',
        'amount' => 50000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif kabul edildi.',
    ]);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $data['businessUser']->id,
        'notifiable_type' => User::class,
        'type' => OfferAcceptedNotification::class,
    ]);

    $notification = DB::table('notifications')
        ->where('notifiable_id', $data['businessUser']->id)
        ->where('notifiable_type', User::class)
        ->where('type', OfferAcceptedNotification::class)
        ->latest('created_at')
        ->first();

    expect($notification)->not->toBeNull();

    $notificationData = json_decode(
        $notification->data,
        true
    );

    expect($notificationData['notification_type'])
        ->toBe(NotificationType::OFFER_ACCEPTED);

    expect($notificationData['offer_id'])
        ->toBe($offer->id);

    expect($notificationData['conversation_id'])
        ->toBe($data['conversation']->id);

    expect($notificationData['business_id'])
        ->toBe($data['business']->id);

    expect($notificationData['patient_profile_id'])
        ->toBe($data['patientProfile']->id);

    expect($notificationData['treatment_id'])
        ->toBe($data['treatment']->id);
});

test('offer creator does not receive notification when offer accepted notification is disabled', function () {
    $data = createNotificationTestData();

    $preferenceService = app(
        BusinessNotificationPreferenceService::class
    );

    $preferenceService->update(
        $data['business'],
        NotificationType::OFFER_ACCEPTED,
        false,
        false
    );

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => $data['treatment']->id,
        'created_by' => $data['businessUser']->id,
        'title' => 'Kapalı bildirim testi',
        'description' => 'Bildirim tercihi kapalı test.',
        'amount' => 50000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif kabul edildi.',
    ]);

    $this->assertDatabaseMissing('notifications', [
        'notifiable_id' => $data['businessUser']->id,
        'notifiable_type' => User::class,
        'type' => OfferAcceptedNotification::class,
    ]);
});