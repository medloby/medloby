<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Test Klinik',
        'slug' => 'test-klinik-' . uniqid(),
    ]);

    $this->branch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'Merkez Şube',
        'slug' => 'merkez-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $this->category = TreatmentCategory::create([
        'name' => 'Saç Ekimi',
        'slug' => 'sac-ekimi-' . uniqid(),
        'is_active' => true,
    ]);
});

function createTreatment($test, array $attributes = []): Treatment
{
    return Treatment::create(array_merge([
        'treatment_category_id' => $test->category->id,
        'name' => 'FUE Saç Ekimi',
        'slug' => 'fue-sac-ekimi-' . uniqid(),
        'is_active' => true,
    ], $attributes));
}

test('can list branch treatments', function () {
    $treatment = createTreatment($this);

    $this->branch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'duration_minutes' => 240,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/treatments"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.id',
            $treatment->id
        )
        ->assertJsonPath(
            'data.treatments.0.pivot.duration_minutes',
            240
        );
});

test('can list only active branch treatments', function () {
    $active = createTreatment($this, [
        'name' => 'Aktif Tedavi',
    ]);

    $inactive = createTreatment($this, [
        'name' => 'Pasif Tedavi',
    ]);

    $this->branch->treatments()->attach($active->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
    ]);

    $this->branch->treatments()->attach($inactive->id, [
        'is_active' => false,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/treatments?active_only=1"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.name',
            'Aktif Tedavi'
        );
});

test('can list only online bookable branch treatments', function () {
    $online = createTreatment($this, [
        'name' => 'Online Tedavi',
    ]);

    $offline = createTreatment($this, [
        'name' => 'Offline Tedavi',
    ]);

    $this->branch->treatments()->attach($online->id, [
        'is_active' => true,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
    ]);

    $this->branch->treatments()->attach($offline->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/treatments?online_bookable=1"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.name',
            'Online Tedavi'
        );
});

test('can list only offer enabled branch treatments', function () {
    $offer = createTreatment($this, [
        'name' => 'Teklifli Tedavi',
    ]);

    $noOffer = createTreatment($this, [
        'name' => 'Teklifsiz Tedavi',
    ]);

    $this->branch->treatments()->attach($offer->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
    ]);

    $this->branch->treatments()->attach($noOffer->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => false,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/treatments?offer_enabled=1"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.treatments')
        ->assertJsonPath(
            'data.treatments.0.name',
            'Teklifli Tedavi'
        );
});

test('business can attach treatment to branch', function () {
    $treatment = createTreatment($this);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/treatments",
        [
            'treatment_id' => $treatment->id,
            'is_active' => true,
            'is_online_bookable' => true,
            'is_offer_enabled' => true,
            'duration_minutes' => 180,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.id',
            $treatment->id
        )
        ->assertJsonPath(
            'data.pivot.is_active',
            true
        )
        ->assertJsonPath(
            'data.pivot.is_online_bookable',
            true
        )
        ->assertJsonPath(
            'data.pivot.is_offer_enabled',
            true
        )
        ->assertJsonPath(
            'data.pivot.duration_minutes',
            180
        );

    $this->assertDatabaseHas('branch_treatment', [
        'branch_id' => $this->branch->id,
        'treatment_id' => $treatment->id,
        'is_active' => true,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'duration_minutes' => 180,
    ]);
});

test('branch treatment uses correct default values', function () {
    $treatment = createTreatment($this);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/treatments",
        [
            'treatment_id' => $treatment->id,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.pivot.is_active',
            true
        )
        ->assertJsonPath(
            'data.pivot.is_online_bookable',
            false
        )
        ->assertJsonPath(
            'data.pivot.is_offer_enabled',
            true
        );
});

test('duplicate treatment cannot be attached to same branch', function () {
    $treatment = createTreatment($this);

    $this->branch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/treatments",
        [
            'treatment_id' => $treatment->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'treatment_id',
        ]);
});

test('non existing treatment cannot be attached', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/treatments",
        [
            'treatment_id' => 999999,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'treatment_id',
        ]);
});

test('can update branch treatment settings', function () {
    $treatment = createTreatment($this);

    $this->branch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
        'duration_minutes' => 120,
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/treatments/{$treatment->id}",
        [
            'is_active' => true,
            'is_online_bookable' => true,
            'is_offer_enabled' => false,
            'duration_minutes' => 180,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.pivot.is_online_bookable',
            true
        )
        ->assertJsonPath(
            'data.pivot.is_offer_enabled',
            false
        )
        ->assertJsonPath(
            'data.pivot.duration_minutes',
            180
        );

    $this->assertDatabaseHas('branch_treatment', [
        'branch_id' => $this->branch->id,
        'treatment_id' => $treatment->id,
        'is_online_bookable' => true,
        'is_offer_enabled' => false,
        'duration_minutes' => 180,
    ]);
});

test('cannot update treatment that is not attached to branch', function () {
    $treatment = createTreatment($this);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/treatments/{$treatment->id}",
        [
            'is_active' => false,
        ]
    );

    $response->assertNotFound();
});

test('can deactivate branch treatment', function () {
    $treatment = createTreatment($this);

    $this->branch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
    ]);

    $response = $this->deleteJson(
        "/api/branches/{$this->branch->id}/treatments/{$treatment->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.pivot.is_active',
            false
        );

    $this->assertDatabaseHas('branch_treatment', [
        'branch_id' => $this->branch->id,
        'treatment_id' => $treatment->id,
        'is_active' => false,
    ]);
});

test('inactive branch treatment remains attached', function () {
    $treatment = createTreatment($this);

    $this->branch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
    ]);

    $this->branch->treatments()->updateExistingPivot(
        $treatment->id,
        [
            'is_active' => false,
        ]
    );

    $this->assertDatabaseHas('branch_treatment', [
        'branch_id' => $this->branch->id,
        'treatment_id' => $treatment->id,
        'is_active' => false,
    ]);
});

test('same treatment can be attached to different branches', function () {
    $secondBranch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'İkinci Şube',
        'slug' => 'ikinci-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $treatment = createTreatment($this);

    $this->branch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
    ]);

    $secondBranch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
        'duration_minutes' => 240,
    ]);

    $this->assertDatabaseHas('branch_treatment', [
        'branch_id' => $this->branch->id,
        'treatment_id' => $treatment->id,
    ]);

    $this->assertDatabaseHas('branch_treatment', [
        'branch_id' => $secondBranch->id,
        'treatment_id' => $treatment->id,
    ]);
});