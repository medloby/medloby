<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\PatientProfile;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Notifications\NewOfferNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createOfferCreationData(): array
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
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Teklif oluşturma testi',
        'status' => 'open',
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Teklif Oluşturma Kategorisi',
        'slug' => 'teklif-olusturma-kategorisi-'.uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Test Tedavisi',
        'slug' => 'test-tedavisi-'.uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    return [
        'business' => $business,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'businessUser' => $businessUser,
        'conversation' => $conversation,
        'category' => $category,
        'treatment' => $treatment,
    ];
}

function offerCreationPayload(array $data, array $overrides = []): array
{
    return array_merge([
        'treatment_id' => $data['treatment']->id,
        'title' => 'Saç Ekimi Teklifi',
        'description' => 'Test teklif açıklaması.',
        'amount' => 25000,
        'currency' => 'try',
        'valid_until' => now()->addDays(3)->toDateTimeString(),
    ], $overrides);
}

test('business user can create offer for conversation', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif başarıyla oluşturuldu.',
    ]);
    $response->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.patient_profile.id', $data['patientProfile']->id)
        ->assertJsonMissingPath('data.patient_profile.phone')
        ->assertJsonMissingPath('data.creator.email');

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => $data['treatment']->id,
        'created_by' => $data['businessUser']->id,
        'title' => 'Saç Ekimi Teklifi',
        'amount' => 25000,
        'currency' => 'TRY',
        'status' => 'pending',
    ]);

    Notification::assertSentTo(
        $data['patientUser'],
        NewOfferNotification::class
    );
});

test('returns 500 without saving an offer or a job when queuing fails and allows retry', function () {
    config(['queue.default' => 'database', 'queue.connections.database.after_commit' => true]);
    $data = createOfferCreationData();
    $url = "/api/conversations/{$data['conversation']->id}/offers";
    $payload = offerCreationPayload($data);
    $failQueue = true;

    Exceptions::fake();
    Event::listen(JobQueued::class, function () use (&$failQueue): void {
        if ($failQueue) {
            throw new RuntimeException('Offer notification queue unavailable.');
        }
    });

    $this->actingAs($data['businessUser'])->postJson($url, $payload)->assertServerError();

    Exceptions::assertReported(RuntimeException::class);
    $this->assertDatabaseCount('offers', 0);
    $this->assertDatabaseCount('jobs', 0);

    $failQueue = false;
    $offerId = $this->postJson($url, $payload)->assertCreated()->json('data.id');

    $this->assertDatabaseCount('offers', 1);
    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseCount('notifications', 0);
    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0, '--tries' => 1])->assertSuccessful();

    $notification = $data['patientUser']->notifications()->sole();
    expect($notification->type)->toBe(NewOfferNotification::class)
        ->and($notification->data['offer_id'])->toBe($offerId);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

test('a delayed new offer notification is sent only to the current patient of an actionable offer', function (string $change) {
    config(['queue.default' => 'database']);
    $data = createOfferCreationData();
    $offerId = $this->actingAs($data['businessUser'])
        ->postJson("/api/conversations/{$data['conversation']->id}/offers", offerCreationPayload($data))
        ->assertCreated()->json('data.id');
    $this->assertDatabaseCount('jobs', 1);

    match ($change) {
        'deadline passed' => $this->travel(4)->days(),
        'answered' => $this->actingAs($data['patientUser'])->postJson("/api/offers/{$offerId}/accept")->assertOk(),
        'clinic suspended' => $data['business']->update(['status' => 'suspended']),
        'patient changed' => $data['patientProfile']->update(['user_id' => User::factory()->create()->id]),
    };

    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0, '--tries' => 1])->assertSuccessful();

    $this->assertDatabaseMissing('notifications', ['type' => NewOfferNotification::class]);
    $this->assertDatabaseHas('offers', ['id' => $offerId]);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with(['deadline passed', 'answered', 'clinic suspended', 'patient changed']);

test('patient cannot create offer', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertForbidden();

    $this->assertDatabaseCount('offers', 0);
});

test('business user from another business cannot create offer', function () {
    $data = createOfferCreationData();

    $otherBusiness = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $otherBusinessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherBusinessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($otherBusinessUser)
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertForbidden();

    $this->assertDatabaseCount('offers', 0);
});

test('inactive business membership cannot create offer', function () {
    $data = createOfferCreationData();

    $data['businessUser']
        ->businessMemberships()
        ->update([
            'is_active' => false,
        ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertForbidden();

    $this->assertDatabaseCount('offers', 0);
});

test('unauthenticated user cannot create offer', function () {
    $data = createOfferCreationData();

    $response = $this
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertUnauthorized();

    $this->assertDatabaseCount('offers', 0);
});

test('offer cannot be created for closed conversation', function () {
    $data = createOfferCreationData();

    $data['conversation']->update([
        'status' => 'closed',
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Kapalı bir görüşmeye teklif oluşturulamaz.',
        ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires treatment', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['treatment_id']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'treatment_id',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires title', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['title']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'title',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires amount', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['amount']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'amount',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires currency', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['currency']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'currency',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects negative amount', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'amount' => -1,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'amount',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects an amount outside the database range before notifying the patient', function () {
    Notification::fake();
    $data = createOfferCreationData();

    $this->actingAs($data['businessUser'])->postJson(
        "/api/conversations/{$data['conversation']->id}/offers",
        offerCreationPayload($data, ['amount' => '10000000000.00'])
    )->assertUnprocessable()->assertJsonValidationErrors('amount');

    $this->assertDatabaseCount('offers', 0);
    Notification::assertNothingSent();
});

test('offer creation rejects invalid currency length', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'currency' => 'TRYX',
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'currency',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects valid until in the past', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'valid_until' => now()->subMinute()->toDateTimeString(),
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'valid_until',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects inactive treatment', function () {
    $data = createOfferCreationData();

    $data['treatment']->update([
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Seçilen tedavi aktif değil.',
        ]);

    $this->assertDatabaseCount('offers', 0);
});

test('a single treatment can be offered only when offers are enabled in its catalog and branch', function (bool $catalogEnabled, bool $branchEnabled, int $status) {
    $data = createOfferCreationData();
    $data['treatment']->update(['is_offer_enabled' => $catalogEnabled]);
    $branch = Branch::factory()->for($data['business'])->create();
    $data['conversation']->update(['branch_id' => $branch->id]);
    $branch->treatments()->attach($data['treatment']->id, ['is_active' => true, 'is_offer_enabled' => $branchEnabled]);

    $this->actingAs($data['businessUser'])->postJson(
        "/api/conversations/{$data['conversation']->id}/offers", offerCreationPayload($data)
    )->assertStatus($status);

    $this->assertDatabaseCount('offers', $status === 201 ? 1 : 0);
})->with([
    'disabled in the catalog' => [false, true, 422],
    'disabled in the branch' => [true, false, 422],
    'enabled in both' => [true, true, 201],
]);

test('offer creation rejects nonexistent treatment', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'treatment_id' => 999999,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'treatment_id',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation accepts optional description', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'description' => null,
            ])
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'description' => null,
    ]);
});

test('offer creation can omit valid until', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['valid_until']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'valid_until' => null,
    ]);
});

test('offer creation stores currency in uppercase', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'currency' => 'usd',
            ])
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'currency' => 'USD',
    ]);
});

test('offer creation uses conversation business branch and patient automatically', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['conversation']->business_id,
        'branch_id' => null,
        'patient_profile_id' => $data['conversation']->patient_profile_id,
        'created_by' => $data['businessUser']->id,
    ]);
});

test('offer creation rejects treatment inactive for conversation branch', function () {
    $data = createOfferCreationData();

    $branchId = DB::table('branches')->insertGetId([
        'business_id' => $data['business']->id,
        'name' => 'Teklif Şubesi',
        'slug' => 'teklif-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $data['conversation']->update([
        'branch_id' => $branchId,
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Seçilen tedavi bu şubede aktif değil.',
        ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation succeeds when treatment is active for conversation branch', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $branchId = DB::table('branches')->insertGetId([
        'business_id' => $data['business']->id,
        'name' => 'Aktif Tedavi Şubesi',
        'slug' => 'aktif-tedavi-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branchId,
        'treatment_id' => $data['treatment']->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $data['conversation']->update([
        'branch_id' => $branchId,
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'branch_id' => $branchId,
        'treatment_id' => $data['treatment']->id,
        'status' => 'pending',
    ]);
});
test('a clinic can send a package offer with a fixed copy of its contents', function () {
    Notification::fake();
    $data = createOfferCreationData();
    $package = $data['business']->treatmentPackages()->create([
        'name' => 'Bakım Paketi',
        'slug' => 'bakim-paketi',
    ]);
    $package->treatments()->attach($data['treatment']->id, ['quantity' => 2]);
    $service = $data['business']->packageServices()->create([
        'name' => 'Transfer',
        'slug' => 'transfer',
        'service_type' => 'transfer',
    ]);
    $package->services()->attach($service->id, ['quantity' => 1]);

    $url = "/api/conversations/{$data['conversation']->id}/offers";
    $payload = offerCreationPayload($data, ['treatment_package_id' => $package->id]);
    unset($payload['treatment_id']);

    $this->actingAs($data['businessUser'])->postJson($url, $payload)
        ->assertCreated()
        ->assertJsonPath('data.treatment_id', null)
        ->assertJsonPath('data.treatment_package_id', $package->id)
        ->assertJsonPath('data.package_snapshot.treatments.0.quantity', 2)
        ->assertJsonPath('data.package_snapshot.services.0.name', 'Transfer');

    Notification::assertSentTo($data['patientUser'], NewOfferNotification::class);

    $package->treatments()->updateExistingPivot($data['treatment']->id, ['quantity' => 3]);
    $this->actingAs($data['patientUser'])->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.0.package_snapshot.treatments.0.quantity', 2);
});

test('a package must be available to the business to be offered', function (array $attributes, bool $foreign) {
    $data = createOfferCreationData();
    $business = $foreign ? Business::factory()->create() : $data['business'];
    $package = $business->treatmentPackages()->create(array_merge([
        'name' => 'Bakım Paketi',
        'slug' => 'bakim-paketi',
    ], $attributes));
    $package->treatments()->attach($data['treatment']->id);

    $url = "/api/conversations/{$data['conversation']->id}/offers";
    $payload = offerCreationPayload($data, ['treatment_package_id' => $package->id]);
    unset($payload['treatment_id']);

    $this->actingAs($data['businessUser'])->postJson($url, $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('treatment_package_id');

    $this->assertDatabaseCount('offers', 0);
})->with([
    'inactive' => [['is_active' => false], false],
    'offers disabled' => [['is_offer_enabled' => false], false],
    'outside validity' => [['valid_until' => '2020-01-01'], false],
    'another business' => [[], true],
]);
