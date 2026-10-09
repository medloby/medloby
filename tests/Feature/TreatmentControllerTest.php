<?php

use App\Models\User;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->category = TreatmentCategory::factory()->create([
        'name' => 'Sac Ekimi',
        'slug' => 'sac-ekimi',
        'is_active' => true,
    ]);
});

test('can list treatments', function () {
    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'FUE Sac Ekimi',
        'slug' => 'fue-sac-ekimi',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/treatments');

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath('data.treatments.0.name', 'FUE Sac Ekimi');
});

test('can list only active treatments', function () {
    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'FUE Sac Ekimi',
        'slug' => 'fue-sac-ekimi',
        'is_active' => true,
    ]);

    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'Pasif Tedavi',
        'slug' => 'pasif-tedavi',
        'is_active' => false,
    ]);

    $response = $this->getJson(
        '/api/treatments?active_only=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.slug',
            'fue-sac-ekimi'
        );
});

test('can list online bookable treatments', function () {
    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'Online Tedavi',
        'slug' => 'online-tedavi',
        'is_online_bookable' => true,
        'is_active' => true,
    ]);

    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'Online Olmayan Tedavi',
        'slug' => 'online-olmayan-tedavi',
        'is_online_bookable' => false,
        'is_active' => true,
    ]);

    $response = $this->getJson(
        '/api/treatments?online_bookable=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.slug',
            'online-tedavi'
        );
});

test('can list offer enabled treatments', function () {
    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'Teklifli Tedavi',
        'slug' => 'teklifli-tedavi',
        'is_offer_enabled' => true,
        'is_active' => true,
    ]);

    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'Teklifsiz Tedavi',
        'slug' => 'teklifsiz-tedavi',
        'is_offer_enabled' => false,
        'is_active' => true,
    ]);

    $response = $this->getJson(
        '/api/treatments?offer_enabled=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.slug',
            'teklifli-tedavi'
        );
});

test('can filter treatments by category', function () {
    $otherCategory = TreatmentCategory::factory()->create([
        'name' => 'Dis',
        'slug' => 'dis',
        'is_active' => true,
    ]);

    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'FUE',
        'slug' => 'fue',
        'is_active' => true,
    ]);

    Treatment::create([
        'treatment_category_id' => $otherCategory->id,
        'name' => 'Implant',
        'slug' => 'implant',
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/treatments?treatment_category_id={$this->category->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.name',
            'FUE'
        );
});

test('can create treatment', function () {
    $response = $this->actingAs($this->user)->postJson('/api/treatments', [
        'treatment_category_id' => $this->category->id,
        'name' => 'DHI Sac Ekimi',
        'slug' => 'dhi-sac-ekimi',
        'description' => 'DHI sac ekimi tedavisi',
        'duration_minutes' => 240,
        'preparation' => 'Muayene gerekir.',
        'aftercare' => 'Kontroller yapilir.',
        'included_services' => 'Greft ekimi ve kontrol',
        'excluded_services' => 'Konaklama',
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'DHI Sac Ekimi')
        ->assertJsonPath('data.slug', 'dhi-sac-ekimi')
        ->assertJsonPath('data.duration_minutes', 240)
        ->assertJsonPath('data.is_online_bookable', true)
        ->assertJsonPath('data.is_offer_enabled', true)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('treatments', [
        'treatment_category_id' => $this->category->id,
        'name' => 'DHI Sac Ekimi',
        'slug' => 'dhi-sac-ekimi',
        'duration_minutes' => 240,
        'is_active' => true,
    ]);
});

test('treatment slug can be generated automatically', function () {
    $response = $this->actingAs($this->user)->postJson('/api/treatments', [
        'treatment_category_id' => $this->category->id,
        'name' => 'Hair Transplant',
        'is_active' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'hair-transplant'
        );
});

test('treatment is inactive by default', function () {
    $response = $this->actingAs($this->user)->postJson('/api/treatments', [
        'treatment_category_id' => $this->category->id,
        'name' => 'Yeni Tedavi',
        'slug' => 'yeni-tedavi',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.is_online_bookable', false)
        ->assertJsonPath('data.is_offer_enabled', true);
});

test('duplicate treatment slug is rejected', function () {
    Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'FUE',
        'slug' => 'fue',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/treatments', [
        'treatment_category_id' => $this->category->id,
        'name' => 'Yeni FUE',
        'slug' => 'fue',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('non existing category is rejected', function () {
    $response = $this->actingAs($this->user)->postJson('/api/treatments', [
        'treatment_category_id' => 999999,
        'name' => 'FUE',
        'slug' => 'fue',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'treatment_category_id',
        ]);
});

test('can show treatment with category', function () {
    $treatment = Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'FUE Sac Ekimi',
        'slug' => 'fue-sac-ekimi',
        'duration_minutes' => 240,
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/treatments/{$treatment->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.name',
            'FUE Sac Ekimi'
        )
        ->assertJsonPath(
            'data.treatment_category.id',
            $this->category->id
        );
});

test('can update treatment', function () {
    $treatment = Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'Eski Tedavi',
        'slug' => 'eski-tedavi',
        'description' => 'Eski aciklama',
        'duration_minutes' => 120,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->putJson(
        "/api/treatments/{$treatment->id}",
        [
            'name' => 'Yeni Tedavi',
            'slug' => 'yeni-tedavi',
            'description' => 'Yeni aciklama',
            'duration_minutes' => 180,
            'is_online_bookable' => true,
            'is_active' => true,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Yeni Tedavi')
        ->assertJsonPath('data.slug', 'yeni-tedavi')
        ->assertJsonPath('data.duration_minutes', 180)
        ->assertJsonPath('data.is_online_bookable', true);

    $this->assertDatabaseHas('treatments', [
        'id' => $treatment->id,
        'name' => 'Yeni Tedavi',
        'slug' => 'yeni-tedavi',
        'duration_minutes' => 180,
    ]);
});

test('can deactivate treatment', function () {
    $treatment = Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'FUE Sac Ekimi',
        'slug' => 'fue-sac-ekimi',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->deleteJson(
        "/api/treatments/{$treatment->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('treatments', [
        'id' => $treatment->id,
        'is_active' => false,
    ]);
});