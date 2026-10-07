<?php

use App\Models\Business;
use App\Models\PackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Test Klinik',
        'slug' => 'test-klinik-' . uniqid(),
    ]);
});

test('can list package services', function () {
    $service = PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Havalimanı Transferi',
        'slug' => 'havalimani-transferi-' . uniqid(),
        'service_type' => 'transfer',
        'description' => 'Havalimanı ve klinik arası transfer.',
        'unit' => 'trip',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson('/api/package-services');

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.services')
        ->assertJsonPath(
            'data.services.0.id',
            $service->id
        )
        ->assertJsonPath(
            'data.services.0.name',
            'Havalimanı Transferi'
        );
});

test('can list only active package services', function () {
    PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Aktif Transfer',
        'slug' => 'aktif-transfer-' . uniqid(),
        'service_type' => 'transfer',
        'is_active' => true,
    ]);

    PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Pasif Transfer',
        'slug' => 'pasif-transfer-' . uniqid(),
        'service_type' => 'transfer',
        'is_active' => false,
    ]);

    $response = $this->getJson(
        '/api/package-services?active_only=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.services')
        ->assertJsonPath(
            'data.services.0.name',
            'Aktif Transfer'
        );
});

test('can filter package services by business', function () {
    $otherBusiness = Business::create([
        'name' => 'Diğer Klinik',
        'slug' => 'diger-klinik-' . uniqid(),
    ]);

    PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Benim Hizmetim',
        'slug' => 'benim-hizmetim-' . uniqid(),
        'service_type' => 'transfer',
    ]);

    PackageService::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer Hizmet',
        'slug' => 'diger-hizmet-' . uniqid(),
        'service_type' => 'transfer',
    ]);

    $response = $this->getJson(
        "/api/package-services?business_id={$this->business->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.services')
        ->assertJsonPath(
            'data.services.0.business_id',
            $this->business->id
        );
});

test('can filter package services by service type', function () {
    PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Transfer Hizmeti',
        'slug' => 'transfer-hizmeti-' . uniqid(),
        'service_type' => 'transfer',
    ]);

    PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Otel Hizmeti',
        'slug' => 'otel-hizmeti-' . uniqid(),
        'service_type' => 'hotel',
    ]);

    $response = $this->getJson(
        '/api/package-services?service_type=hotel'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.services')
        ->assertJsonPath(
            'data.services.0.service_type',
            'hotel'
        );
});

test('can create package service', function () {
    $response = $this->postJson(
        '/api/package-services',
        [
            'business_id' => $this->business->id,
            'name' => 'VIP Araç Transferi',
            'service_type' => 'transfer',
            'description' => 'VIP araç ile özel transfer.',
            'unit' => 'trip',
            'sort_order' => 2,
            'is_active' => true,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.business_id',
            $this->business->id
        )
        ->assertJsonPath(
            'data.name',
            'VIP Araç Transferi'
        )
        ->assertJsonPath(
            'data.service_type',
            'transfer'
        )
        ->assertJsonPath(
            'data.unit',
            'trip'
        )
        ->assertJsonPath(
            'data.is_active',
            true
        )
        ->assertJsonPath(
            'data.sort_order',
            2
        );

    $this->assertDatabaseHas('package_services', [
        'business_id' => $this->business->id,
        'name' => 'VIP Araç Transferi',
        'service_type' => 'transfer',
        'unit' => 'trip',
    ]);
});

test('package service slug is generated automatically', function () {
    $response = $this->postJson(
        '/api/package-services',
        [
            'business_id' => $this->business->id,
            'name' => 'Havalimanı Karşılama',
            'service_type' => 'airport_pickup',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'havalimani-karsilama'
        );
});

test('duplicate package service slug gets a unique slug', function () {
    PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Transfer',
        'slug' => 'transfer',
        'service_type' => 'transfer',
    ]);

    $response = $this->postJson(
        '/api/package-services',
        [
            'business_id' => $this->business->id,
            'name' => 'Transfer',
            'slug' => 'transfer',
            'service_type' => 'transfer',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'transfer-2'
        );
});

test('global package service can be created without business', function () {
    $response = $this->postJson(
        '/api/package-services',
        [
            'name' => 'Genel Havalimanı Transferi',
            'service_type' => 'transfer',
            'unit' => 'trip',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.business_id',
            null
        )
        ->assertJsonPath(
            'data.name',
            'Genel Havalimanı Transferi'
        );

    $this->assertDatabaseHas('package_services', [
        'business_id' => null,
        'name' => 'Genel Havalimanı Transferi',
        'service_type' => 'transfer',
    ]);
});

test('package service uses correct default values', function () {
    $response = $this->postJson(
        '/api/package-services',
        [
            'business_id' => $this->business->id,
            'name' => 'Standart Transfer',
            'service_type' => 'transfer',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.is_active',
            true
        )
        ->assertJsonPath(
            'data.sort_order',
            0
        );
});

test('can show package service with relationships', function () {
    $service = PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Otel Konaklama',
        'slug' => 'otel-konaklama-' . uniqid(),
        'service_type' => 'hotel',
        'unit' => 'night',
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/package-services/{$service->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.id',
            $service->id
        )
        ->assertJsonPath(
            'data.business.id',
            $this->business->id
        );
});

test('can update package service', function () {
    $service = PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Eski Transfer',
        'slug' => 'eski-transfer-' . uniqid(),
        'service_type' => 'transfer',
        'unit' => 'trip',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->putJson(
        "/api/package-services/{$service->id}",
        [
            'name' => 'Güncellenmiş VIP Transfer',
            'service_type' => 'vip_transfer',
            'unit' => 'service',
            'sort_order' => 5,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.name',
            'Güncellenmiş VIP Transfer'
        )
        ->assertJsonPath(
            'data.service_type',
            'vip_transfer'
        )
        ->assertJsonPath(
            'data.unit',
            'service'
        )
        ->assertJsonPath(
            'data.sort_order',
            5
        );

    $this->assertDatabaseHas('package_services', [
        'id' => $service->id,
        'name' => 'Güncellenmiş VIP Transfer',
        'service_type' => 'vip_transfer',
        'unit' => 'service',
        'sort_order' => 5,
    ]);
});

test('can deactivate package service', function () {
    $service = PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Pasifleştirilecek Hizmet',
        'slug' => 'pasif-hizmet-' . uniqid(),
        'service_type' => 'transfer',
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/package-services/{$service->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.is_active',
            false
        );

    $this->assertDatabaseHas('package_services', [
        'id' => $service->id,
        'is_active' => false,
    ]);
});

test('inactive package service remains in database after deactivation', function () {
    $service = PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Pasif Hizmet',
        'slug' => 'pasif-hizmet-' . uniqid(),
        'service_type' => 'transfer',
        'is_active' => true,
    ]);

    $this->deleteJson(
        "/api/package-services/{$service->id}"
    );

    $this->assertDatabaseHas('package_services', [
        'id' => $service->id,
        'is_active' => false,
    ]);
});

test('same slug can exist for different businesses', function () {
    $otherBusiness = Business::create([
        'name' => 'İkinci Klinik',
        'slug' => 'ikinci-klinik-' . uniqid(),
    ]);

    PackageService::create([
        'business_id' => $this->business->id,
        'name' => 'Transfer',
        'slug' => 'transfer',
        'service_type' => 'transfer',
    ]);

    $response = $this->postJson(
        '/api/package-services',
        [
            'business_id' => $otherBusiness->id,
            'name' => 'Transfer',
            'slug' => 'transfer',
            'service_type' => 'transfer',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'transfer'
        );
});