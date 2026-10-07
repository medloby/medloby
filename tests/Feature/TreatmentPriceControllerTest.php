<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPrice;
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

test('can list treatment prices', function () {
    $price = TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/treatment-prices');

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath(
            'data.prices.0.id',
            $price->id
        )
        ->assertJsonPath(
            'data.prices.0.currency',
            'TRY'
        );
});

test('can list only active treatment prices', function () {
    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 45000,
        'currency' => 'TRY',
        'is_active' => false,
    ]);

    $response = $this->getJson(
        '/api/treatment-prices?active_only=1'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath(
            'data.prices.0.is_active',
            true
        );
});

test('can filter treatment prices by business', function () {
    $otherBusiness = Business::create([
        'name' => 'Other Klinik',
        'slug' => 'other-klinik-' . uniqid(),
    ]);

    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
    ]);

    TreatmentPrice::create([
        'business_id' => $otherBusiness->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 60000,
        'currency' => 'TRY',
    ]);

    $response = $this->getJson(
        "/api/treatment-prices?business_id={$this->business->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath(
            'data.prices.0.business_id',
            $this->business->id
        );
});

test('can filter treatment prices by treatment', function () {
    $otherTreatment = Treatment::create([
        'treatment_category_id' => $this->category->id,
        'name' => 'PRP',
        'slug' => 'prp-' . uniqid(),
        'is_active' => true,
    ]);

    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
    ]);

    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $otherTreatment->id,
        'price_type' => 'fixed',
        'price' => 15000,
        'currency' => 'TRY',
    ]);

    $response = $this->getJson(
        "/api/treatment-prices?treatment_id={$this->treatment->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath(
            'data.prices.0.treatment_id',
            $this->treatment->id
        );
});

test('can filter treatment prices by branch', function () {
    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'branch_id' => $this->branch->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
    ]);

    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 60000,
        'currency' => 'TRY',
    ]);

    $response = $this->getJson(
        "/api/treatment-prices?branch_id={$this->branch->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath(
            'data.prices.0.branch_id',
            $this->branch->id
        );
});

test('can filter treatment prices by currency', function () {
    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
    ]);

    TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 2000,
        'currency' => 'EUR',
    ]);

    $response = $this->getJson(
        '/api/treatment-prices?currency=eur'
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath(
            'data.prices.0.currency',
            'EUR'
        );
});

test('can create fixed treatment price', function () {
    $response = $this->postJson(
        '/api/treatment-prices',
        [
            'business_id' => $this->business->id,
            'treatment_id' => $this->treatment->id,
            'branch_id' => $this->branch->id,
            'price_type' => 'fixed',
            'price' => 50000,
            'currency' => 'TRY',
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
            'data.treatment_id',
            $this->treatment->id
        )
        ->assertJsonPath(
            'data.price',
            '50000.00'
        )
        ->assertJsonPath(
            'data.currency',
            'TRY'
        )
        ->assertJsonPath(
            'data.is_active',
            true
        );

    $this->assertDatabaseHas('treatment_prices', [
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'branch_id' => $this->branch->id,
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);
});

test('currency is automatically normalized to uppercase', function () {
    $response = $this->postJson(
        '/api/treatment-prices',
        [
            'business_id' => $this->business->id,
            'treatment_id' => $this->treatment->id,
            'price_type' => 'fixed',
            'price' => 2000,
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

test('can create treatment price range', function () {
    $response = $this->postJson(
        '/api/treatment-prices',
        [
            'business_id' => $this->business->id,
            'treatment_id' => $this->treatment->id,
            'price_type' => 'range',
            'min_price' => 40000,
            'max_price' => 60000,
            'currency' => 'TRY',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.min_price',
            '40000.00'
        )
        ->assertJsonPath(
            'data.max_price',
            '60000.00'
        );
});

test('max price cannot be lower than min price', function () {
    $response = $this->postJson(
        '/api/treatment-prices',
        [
            'business_id' => $this->business->id,
            'treatment_id' => $this->treatment->id,
            'price_type' => 'range',
            'min_price' => 60000,
            'max_price' => 40000,
            'currency' => 'TRY',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'max_price',
        ]);
});

test('valid until cannot be before valid from', function () {
    $response = $this->postJson(
        '/api/treatment-prices',
        [
            'business_id' => $this->business->id,
            'treatment_id' => $this->treatment->id,
            'price_type' => 'fixed',
            'price' => 50000,
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

test('can create doctor specific treatment price', function () {
    $person = \App\Models\Person::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Test',
        'last_name' => 'Doctor',
    ]);

    $doctor = Doctor::create([
        'business_id' => $this->business->id,
        'person_id' => $person->id,
        'status' => 'active',
    ]);

    $response = $this->postJson(
        '/api/treatment-prices',
        [
            'business_id' => $this->business->id,
            'treatment_id' => $this->treatment->id,
            'branch_id' => $this->branch->id,
            'doctor_id' => $doctor->id,
            'price_type' => 'fixed',
            'price' => 75000,
            'currency' => 'TRY',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.doctor_id',
            $doctor->id
        );
});

test('can show treatment price with relationships', function () {
    $price = TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'branch_id' => $this->branch->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
    ]);

    $response = $this->getJson(
        "/api/treatment-prices/{$price->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $price->id
        )
        ->assertJsonPath(
            'data.business.id',
            $this->business->id
        )
        ->assertJsonPath(
            'data.treatment.id',
            $this->treatment->id
        )
        ->assertJsonPath(
            'data.branch.id',
            $this->branch->id
        );
});

test('can update treatment price', function () {
    $price = TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'branch_id' => $this->branch->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/treatment-prices/{$price->id}",
        [
            'price' => 65000,
            'currency' => 'EUR',
            'is_active' => false,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.price',
            '65000.00'
        )
        ->assertJsonPath(
            'data.currency',
            'EUR'
        )
        ->assertJsonPath(
            'data.is_active',
            false
        );

    $this->assertDatabaseHas('treatment_prices', [
        'id' => $price->id,
        'price' => 65000,
        'currency' => 'EUR',
        'is_active' => false,
    ]);
});

test('can deactivate treatment price', function () {
    $price = TreatmentPrice::create([
        'business_id' => $this->business->id,
        'treatment_id' => $this->treatment->id,
        'price_type' => 'fixed',
        'price' => 50000,
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/treatment-prices/{$price->id}"
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

    $this->assertDatabaseHas('treatment_prices', [
        'id' => $price->id,
        'is_active' => false,
    ]);
});