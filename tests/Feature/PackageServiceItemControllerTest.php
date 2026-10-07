<?php

use App\Models\Business;
use App\Models\PackageService;
use App\Models\PackageServiceItem;
use App\Models\TreatmentPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Test Klinik',
        'slug' => 'test-klinik-' . uniqid(),
    ]);

    $this->package = TreatmentPackage::create([
        'business_id' => $this->business->id,
        'name' => 'VIP Saç Ekimi Paketi',
        'slug' => 'vip-sac-ekimi-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 75000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $this->service = PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Havalimanı Transferi',
        'slug' => 'havalimani-transferi-' . uniqid(),
        'service_type' => 'transfer',
        'unit' => 'trip',
        'is_active' => true,
    ]);

    $this->secondService = PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Otel Konaklama',
        'slug' => 'otel-konaklama-' . uniqid(),
        'service_type' => 'hotel',
        'unit' => 'night',
        'is_active' => true,
    ]);
});

test('can list package service items', function () {
    $item = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
        'quantity' => 2,
        'notes' => 'Gidiş ve dönüş transferi.',
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/service-items"
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
            'data.0.package_service_id',
            $this->service->id
        )
        ->assertJsonPath(
            'data.0.quantity',
            2
        )
        ->assertJsonPath(
            'data.0.notes',
            'Gidiş ve dönüş transferi.'
        );
});

test('package service items are returned in sort order', function () {
    $secondItem = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->secondService->id,
        'quantity' => 5,
        'sort_order' => 2,
    ]);

    $firstItem = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
        'quantity' => 1,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/service-items"
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

test('can attach service to package', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/service-items",
        [
            'package_service_id' => $this->service->id,
            'quantity' => 2,
            'notes' => 'Özel transfer.',
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
            'data.package_service_id',
            $this->service->id
        )
        ->assertJsonPath(
            'data.quantity',
            2
        )
        ->assertJsonPath(
            'data.notes',
            'Özel transfer.'
        )
        ->assertJsonPath(
            'data.sort_order',
            1
        );

    $this->assertDatabaseHas(
        'package_service_items',
        [
            'treatment_package_id' => $this->package->id,
            'package_service_id' => $this->service->id,
            'quantity' => 2,
            'sort_order' => 1,
        ]
    );
});

test('package service item uses correct default values', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/service-items",
        [
            'package_service_id' => $this->service->id,
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

test('duplicate service cannot be added to same package', function () {
    PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
    ]);

    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/service-items",
        [
            'package_service_id' => $this->service->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseCount(
        'package_service_items',
        1
    );
});

test('non existing service cannot be added', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/service-items",
        [
            'package_service_id' => 999999,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'package_service_id',
        ]);
});

test('quantity must be at least one', function () {
    $response = $this->postJson(
        "/api/treatment-packages/{$this->package->id}/service-items",
        [
            'package_service_id' => $this->service->id,
            'quantity' => 0,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'quantity',
        ]);
});

test('can show package service item', function () {
    $item = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
        'quantity' => 1,
        'notes' => 'Transfer hizmeti.',
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$item->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $item->id
        )
        ->assertJsonPath(
            'data.package_service.id',
            $this->service->id
        );
});

test('cannot show item belonging to another package', function () {
    $otherPackage = TreatmentPackage::create([
        'business_id' => $this->business->id,
        'name' => 'Diğer Paket',
        'slug' => 'diger-paket-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $item = PackageServiceItem::create([
        'treatment_package_id' => $otherPackage->id,
        'package_service_id' => $this->service->id,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$item->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can update package service item', function () {
    $item = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
        'quantity' => 1,
        'notes' => 'Eski not',
        'sort_order' => 1,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$item->id}",
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
        'package_service_items',
        [
            'id' => $item->id,
            'quantity' => 3,
            'notes' => 'Güncellenmiş not',
            'sort_order' => 5,
        ]
    );
});

test('can change service on package item', function () {
    $item = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$item->id}",
        [
            'package_service_id' => $this->secondService->id,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.package_service_id',
            $this->secondService->id
        );
});

test('cannot update item to duplicate service in same package', function () {
    $firstItem = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
    ]);

    PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->secondService->id,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$firstItem->id}",
        [
            'package_service_id' => $this->secondService->id,
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
        'name' => 'Diğer Paket',
        'slug' => 'diger-paket-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $item = PackageServiceItem::create([
        'treatment_package_id' => $otherPackage->id,
        'package_service_id' => $this->service->id,
    ]);

    $response = $this->putJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$item->id}",
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

test('can remove service from package', function () {
    $item = PackageServiceItem::create([
        'treatment_package_id' => $this->package->id,
        'package_service_id' => $this->service->id,
        'quantity' => 1,
    ]);

    $response = $this->deleteJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$item->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        );

    $this->assertDatabaseMissing(
        'package_service_items',
        [
            'id' => $item->id,
        ]
    );
});

test('cannot remove item belonging to another package', function () {
    $otherPackage = TreatmentPackage::create([
        'business_id' => $this->business->id,
        'name' => 'Diğer Paket',
        'slug' => 'diger-paket-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $item = PackageServiceItem::create([
        'treatment_package_id' => $otherPackage->id,
        'package_service_id' => $this->service->id,
    ]);

    $response = $this->deleteJson(
        "/api/treatment-packages/{$this->package->id}/service-items/{$item->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseHas(
        'package_service_items',
        [
            'id' => $item->id,
        ]
    );
});