<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPackage;
use App\Models\Treatment;
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
});

function createPackage($test, array $attributes = []): TreatmentPackage
{
    return TreatmentPackage::create(array_merge([
        'business_id' => $test->business->id,
        'branch_id' => $test->branch->id,
        'name' => 'Premium Saç Ekimi Paketi',
        'slug' => 'premium-sac-ekimi-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 50000,
        'currency' => 'TRY',
        'is_offer_enabled' => true,
        'is_active' => true,
    ], $attributes));
}

test('can list treatment packages', function () {
    $package = createPackage($this);

    $response = $this->getJson('/api/treatment-packages');

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath(
            'data.packages.0.id',
            $package->id
        )
        ->assertJsonPath(
            'data.packages.0.name',
            'Premium Saç Ekimi Paketi'
        );
});

test('can list only active treatment packages', function () {
    createPackage($this, [
        'name' => 'Aktif Paket',
        'slug' => 'aktif-paket-' . uniqid(),
        'is_active' => true,
    ]);

    createPackage($this, [
        'name' => 'Pasif Paket',
        'slug' => 'pasif-paket-' . uniqid(),
        'is_active' => false,
    ]);

    $response = $this->getJson(
        '/api/treatment-packages?active_only=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath(
            'data.packages.0.name',
            'Aktif Paket'
        );
});

test('can list only offer enabled treatment packages', function () {
    createPackage($this, [
        'name' => 'Teklifli Paket',
        'slug' => 'teklifli-paket-' . uniqid(),
        'is_offer_enabled' => true,
    ]);

    createPackage($this, [
        'name' => 'Teklifsiz Paket',
        'slug' => 'teklifsiz-paket-' . uniqid(),
        'is_offer_enabled' => false,
    ]);

    $response = $this->getJson(
        '/api/treatment-packages?offer_enabled=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath(
            'data.packages.0.name',
            'Teklifli Paket'
        );
});

test('can filter treatment packages by business', function () {
    $otherBusiness = Business::create([
        'name' => 'Other Klinik',
        'slug' => 'other-klinik-' . uniqid(),
    ]);

    createPackage($this);

    TreatmentPackage::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer Klinik Paketi',
        'slug' => 'diger-klinik-paketi-' . uniqid(),
        'package_type' => 'treatment',
        'price' => 60000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages?business_id={$this->business->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath(
            'data.packages.0.business_id',
            $this->business->id
        );
});

test('can filter treatment packages by branch', function () {
    $secondBranch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'İkinci Şube',
        'slug' => 'ikinci-sube-' . uniqid(),
        'status' => 'active',
    ]);

    createPackage($this);

    createPackage($this, [
        'name' => 'İkinci Şube Paketi',
        'slug' => 'ikinci-sube-paketi-' . uniqid(),
        'branch_id' => $secondBranch->id,
    ]);

    $response = $this->getJson(
        "/api/treatment-packages?branch_id={$this->branch->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath(
            'data.packages.0.branch_id',
            $this->branch->id
        );
});

test('can filter treatment packages by package type', function () {
    createPackage($this, [
        'name' => 'Treatment Paketi',
        'slug' => 'treatment-paketi-' . uniqid(),
        'package_type' => 'treatment',
    ]);

    createPackage($this, [
        'name' => 'Health Tourism Paketi',
        'slug' => 'health-tourism-paketi-' . uniqid(),
        'package_type' => 'health_tourism',
    ]);

    $response = $this->getJson(
        '/api/treatment-packages?package_type=health_tourism'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath(
            'data.packages.0.package_type',
            'health_tourism'
        );
});

test('can filter treatment packages by currency', function () {
    createPackage($this, [
        'name' => 'TRY Paket',
        'slug' => 'try-paket-' . uniqid(),
        'currency' => 'TRY',
    ]);

    createPackage($this, [
        'name' => 'EUR Paket',
        'slug' => 'eur-paket-' . uniqid(),
        'currency' => 'EUR',
    ]);

    $response = $this->getJson(
        '/api/treatment-packages?currency=eur'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath(
            'data.packages.0.currency',
            'EUR'
        );
});

test('can create treatment package', function () {
    $response = $this->postJson(
        '/api/treatment-packages',
        [
            'business_id' => $this->business->id,
            'branch_id' => $this->branch->id,
            'name' => 'VIP Saç Ekimi Paketi',
            'description' => 'Kapsamlı saç ekimi paketi.',
            'package_type' => 'treatment',
            'price' => 75000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'includes_hotel' => true,
            'includes_transfer' => true,
            'is_offer_enabled' => true,
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
            'data.branch_id',
            $this->branch->id
        )
        ->assertJsonPath(
            'data.name',
            'VIP Saç Ekimi Paketi'
        )
        ->assertJsonPath(
            'data.price',
            '75000.00'
        )
        ->assertJsonPath(
            'data.currency',
            'TRY'
        )
        ->assertJsonPath(
            'data.duration_days',
            5
        )
        ->assertJsonPath(
            'data.includes_hotel',
            true
        )
        ->assertJsonPath(
            'data.includes_transfer',
            true
        )
        ->assertJsonPath(
            'data.is_active',
            true
        );

    $this->assertDatabaseHas('treatment_packages', [
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'name' => 'VIP Saç Ekimi Paketi',
        'price' => 75000,
        'currency' => 'TRY',
        'duration_days' => 5,
        'includes_hotel' => true,
        'includes_transfer' => true,
        'is_active' => true,
    ]);
});

test('package slug is generated automatically', function () {
    $response = $this->postJson(
        '/api/treatment-packages',
        [
            'business_id' => $this->business->id,
            'branch_id' => $this->branch->id,
            'name' => 'Premium Saç Ekimi Paketi',
            'price' => 50000,
            'currency' => 'TRY',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'premium-sac-ekimi-paketi'
        );
});

test('duplicate package slug gets a unique slug', function () {
    createPackage($this, [
        'name' => 'Premium Paket',
        'slug' => 'premium-paket',
    ]);

    $response = $this->postJson(
        '/api/treatment-packages',
        [
            'business_id' => $this->business->id,
            'branch_id' => $this->branch->id,
            'name' => 'Premium Paket',
            'slug' => 'premium-paket',
            'price' => 60000,
            'currency' => 'TRY',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'premium-paket-2'
        );
});

test('currency is automatically normalized to uppercase', function () {
    $response = $this->postJson(
        '/api/treatment-packages',
        [
            'business_id' => $this->business->id,
            'branch_id' => $this->branch->id,
            'name' => 'Euro Paket',
            'price' => 2500,
            'currency' => 'eur',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.currency',
            'EUR'
        );
});

test('package uses correct default values', function () {
    $response = $this->postJson(
        '/api/treatment-packages',
        [
            'business_id' => $this->business->id,
            'branch_id' => $this->branch->id,
            'name' => 'Standart Paket',
            'price' => 30000,
            'currency' => 'TRY',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.package_type',
            'treatment'
        )
        ->assertJsonPath(
            'data.includes_hotel',
            false
        )
        ->assertJsonPath(
            'data.includes_transfer',
            false
        )
        ->assertJsonPath(
            'data.is_offer_enabled',
            true
        )
        ->assertJsonPath(
            'data.is_active',
            true
        );
});

test('valid until cannot be before valid from', function () {
    $response = $this->postJson(
        '/api/treatment-packages',
        [
            'business_id' => $this->business->id,
            'branch_id' => $this->branch->id,
            'name' => 'Tarihli Paket',
            'price' => 40000,
            'currency' => 'TRY',
            'valid_from' => '2026-10-20',
            'valid_until' => '2026-10-01',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'valid_until',
        ]);
});

test('can show treatment package with relationships', function () {
    $package = createPackage($this);

    $response = $this->getJson(
        "/api/treatment-packages/{$package->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $package->id
        )
        ->assertJsonPath(
            'data.business.id',
            $this->business->id
        )
        ->assertJsonPath(
            'data.branch.id',
            $this->branch->id
        );
});

test('can update treatment package', function () {
    $package = createPackage($this);

    $response = $this->putJson(
        "/api/treatment-packages/{$package->id}",
        [
            'name' => 'Güncellenmiş VIP Paket',
            'price' => 85000,
            'currency' => 'EUR',
            'duration_days' => 7,
            'includes_hotel' => true,
            'includes_transfer' => true,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.name',
            'Güncellenmiş VIP Paket'
        )
        ->assertJsonPath(
            'data.price',
            '85000.00'
        )
        ->assertJsonPath(
            'data.currency',
            'EUR'
        )
        ->assertJsonPath(
            'data.duration_days',
            7
        )
        ->assertJsonPath(
            'data.includes_hotel',
            true
        )
        ->assertJsonPath(
            'data.includes_transfer',
            true
        );

    $this->assertDatabaseHas('treatment_packages', [
        'id' => $package->id,
        'name' => 'Güncellenmiş VIP Paket',
        'price' => 85000,
        'currency' => 'EUR',
        'duration_days' => 7,
    ]);
});

test('can deactivate treatment package', function () {
    $package = createPackage($this);

    $response = $this->deleteJson(
        "/api/treatment-packages/{$package->id}"
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

    $this->assertDatabaseHas('treatment_packages', [
        'id' => $package->id,
        'is_active' => false,
    ]);
});

test('inactive treatment package remains in database after deactivation', function () {
    $package = createPackage($this);

    $this->deleteJson(
        "/api/treatment-packages/{$package->id}"
    );

    $this->assertDatabaseHas('treatment_packages', [
        'id' => $package->id,
        'is_active' => false,
    ]);
});