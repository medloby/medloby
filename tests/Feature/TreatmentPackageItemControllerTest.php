<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPackage;
use App\Models\TreatmentPackageItem;
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

    $this->treatment = Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'FUE Saç Ekimi',
        'slug' => 'fue-sac-ekimi-' . uniqid(),
        'is_active' => true,
    ]);

    $this->secondTreatment = Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'PRP Tedavisi',
        'slug' => 'prp-tedavisi-' . uniqid(),
        'is_active' => true,
    ]);

    $this->package = TreatmentPackage::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'name' => 'VIP Saç Ekimi Paketi',
        'slug' => 'vip-sac-ekimi-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 75000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);
});

test('can list treatment package items', function () {
    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
        'quantity' => 1,
        'notes' => 'Ana tedavi',
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/items"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $item->id
        )
        ->assertJsonPath(
            'data.0.treatment_id',
            $this->treatment->id
        )
        ->assertJsonPath(
            'data.0.quantity',
            1
        )
        ->assertJsonPath(
            'data.0.notes',
            'Ana tedavi'
        );
});

test('package items are returned in sort order', function () {
    $secondItem = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->secondTreatment->id,
        'quantity' => 2,
        'sort_order' => 2,
    ]);

    $firstItem = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
        'quantity' => 1,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/items"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.0.id',
            $firstItem->id
        )
        ->assertJsonPath(
            'data.1.id',
            $secondItem->id
        );
});

test('can attach treatment to package', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/items",
        [
            'treatment_id' => $this->treatment->id,
            'quantity' => 2,
            'notes' => 'İki seans uygulanacak.',
            'sort_order' => 1,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.treatment_package_id',
            $this->package->id
        )
        ->assertJsonPath(
            'data.treatment_id',
            $this->treatment->id
        )
        ->assertJsonPath(
            'data.quantity',
            2
        )
        ->assertJsonPath(
            'data.notes',
            'İki seans uygulanacak.'
        )
        ->assertJsonPath(
            'data.sort_order',
            1
        );

    $this->assertDatabaseHas(
        'treatment_package_items',
        [
            'treatment_package_id' => $this->package->id,
            'treatment_id' => $this->treatment->id,
            'quantity' => 2,
            'sort_order' => 1,
        ]
    );
});

test('package item uses correct default values', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/items",
        [
            'treatment_id' => $this->treatment->id,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.quantity',
            1
        )
        ->assertJsonPath(
            'data.sort_order',
            0
        )
        ->assertJsonPath(
            'data.notes',
            null
        );
});

test('duplicate treatment cannot be added to same package', function () {
    TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
    ]);

    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/items",
        [
            'treatment_id' => $this->treatment->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseCount(
        'treatment_package_items',
        1
    );
});

test('non existing treatment cannot be added', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/items",
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

test('quantity must be at least one', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/items",
        [
            'treatment_id' => $this->treatment->id,
            'quantity' => 0,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'quantity',
        ]);
});

test('can show treatment package item', function () {
    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
        'quantity' => 1,
        'notes' => 'Ana tedavi',
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/items/{$item->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $item->id
        )
        ->assertJsonPath(
            'data.treatment.id',
            $this->treatment->id
        );
});

test('cannot show item belonging to another package', function () {
    $otherPackage = TreatmentPackage::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'name' => 'Diğer Paket',
        'slug' => 'diger-paket-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $otherPackage->id,
        'treatment_id' => $this->treatment->id,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/items/{$item->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can update treatment package item', function () {
    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
        'quantity' => 1,
        'notes' => 'Eski not',
        'sort_order' => 1,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/items/{$item->id}",
        [
            'quantity' => 3,
            'notes' => 'Güncellenmiş not',
            'sort_order' => 5,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.quantity',
            3
        )
        ->assertJsonPath(
            'data.notes',
            'Güncellenmiş not'
        )
        ->assertJsonPath(
            'data.sort_order',
            5
        );

    $this->assertDatabaseHas(
        'treatment_package_items',
        [
            'id' => $item->id,
            'quantity' => 3,
            'notes' => 'Güncellenmiş not',
            'sort_order' => 5,
        ]
    );
});

test('can change treatment on package item', function () {
    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/items/{$item->id}",
        [
            'treatment_id' => $this->secondTreatment->id,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.treatment_id',
            $this->secondTreatment->id
        );
});

test('cannot update item to duplicate treatment in same package', function () {
    $firstItem = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
    ]);

    TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->secondTreatment->id,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/items/{$firstItem->id}",
        [
            'treatment_id' => $this->secondTreatment->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});

test('cannot update item that belongs to another package', function () {
    $otherPackage = TreatmentPackage::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'name' => 'Diğer Paket',
        'slug' => 'diger-paket-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $otherPackage->id,
        'treatment_id' => $this->treatment->id,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/items/{$item->id}",
        [
            'quantity' => 5,
        ]
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can remove treatment from package', function () {
    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $this->package->id,
        'treatment_id' => $this->treatment->id,
        'quantity' => 1,
    ]);

    $response = $this->deleteJson(
        "/api/treatment-packages/{$this->package->id}/items/{$item->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        );

    $this->assertDatabaseMissing(
        'treatment_package_items',
        [
            'id' => $item->id,
        ]
    );
});

test('cannot remove item belonging to another package', function () {
    $otherPackage = TreatmentPackage::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'name' => 'Diğer Paket',
        'slug' => 'diger-paket-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $item = TreatmentPackageItem::create([
        'treatment_package_id' => $otherPackage->id,
        'treatment_id' => $this->treatment->id,
    ]);

    $response = $this->deleteJson(
        "/api/treatment-packages/{$this->package->id}/items/{$item->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseHas(
        'treatment_package_items',
        [
            'id' => $item->id,
        ]
    );
});