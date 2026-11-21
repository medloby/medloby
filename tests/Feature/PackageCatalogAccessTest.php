<?php

use App\Models\Business;
use App\Models\PackageService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->package = $this->business->treatmentPackages()->create([
        'name' => 'Treatment Package',
        'slug' => 'treatment-package',
        'is_active' => true,
    ]);
    $this->service = $this->business->packageServices()->create([
        'name' => 'Transfer',
        'slug' => 'transfer',
        'service_type' => 'transfer',
        'is_active' => true,
    ]);
});

test('guests can read active catalog entries but cannot change them', function () {
    $this->getJson("/api/treatment-packages/{$this->package->id}")->assertOk();
    $this->getJson("/api/package-services/{$this->service->id}")->assertOk();
    $this->deleteJson("/api/treatment-packages/{$this->package->id}")->assertUnauthorized();
    $this->putJson("/api/package-services/{$this->service->id}")->assertUnauthorized();
    $this->postJson("/api/treatment-packages/{$this->package->id}/items")->assertUnauthorized();
    $this->postJson("/api/treatment-packages/{$this->package->id}/service-items")->assertUnauthorized();
});

test('public package and service responses omit inactive catalog records and links', function () {
    $this->package->services()->attach($this->service);
    $this->service->update(['is_active' => false]);

    $this->getJson('/api/package-services')->assertOk()->assertJsonCount(0, 'data.services');
    $this->getJson("/api/package-services/{$this->service->id}")->assertNotFound();
    $this->getJson("/api/treatment-packages/{$this->package->id}")->assertOk()->assertJsonCount(0, 'data.services');
    $this->getJson("/api/treatment-packages/{$this->package->id}/service-items")->assertOk()->assertJsonCount(0, 'data');

    $this->package->update(['is_active' => false]);

    $this->getJson('/api/treatment-packages')->assertOk()->assertJsonCount(0, 'data.packages');
    $this->getJson("/api/treatment-packages/{$this->package->id}")->assertNotFound();
    $this->getJson("/api/treatment-packages/{$this->package->id}/items")->assertNotFound();
    $this->getJson("/api/treatment-packages/{$this->package->id}/service-items")->assertNotFound();
});

test('catalog writes require active ownership in the target business', function (string $role, bool $active, bool $sameBusiness) {
    $user = User::factory()->create();
    $business = $sameBusiness ? $this->business : Business::factory()->create();
    $business->memberships()->create([
        'user_id' => $user->id,
        'role' => $role,
        'is_active' => $active,
    ]);
    $this->actingAs($user, 'sanctum');

    $this->putJson("/api/treatment-packages/{$this->package->id}", ['name' => 'Updated'])->assertForbidden();
    $this->deleteJson("/api/package-services/{$this->service->id}")->assertForbidden();
    $this->postJson("/api/treatment-packages/{$this->package->id}/items")->assertForbidden();
    $this->postJson("/api/treatment-packages/{$this->package->id}/service-items")->assertForbidden();
})->with([
    'another business owner' => ['business_owner', true, false],
    'inactive owner' => ['business_owner', false, true],
    'active staff' => ['staff', true, true],
]);

test('owners cannot transfer records to an unmanaged business or manage global services', function () {
    $owner = User::factory()->create();
    $this->business->memberships()->create([
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);
    $otherBusiness = Business::factory()->create();
    $globalService = PackageService::create([
        'name' => 'Global Transfer',
        'slug' => 'global-transfer',
        'service_type' => 'transfer',
    ]);
    $this->actingAs($owner, 'sanctum');

    $this->putJson("/api/treatment-packages/{$this->package->id}", [
        'business_id' => $otherBusiness->id,
    ])->assertForbidden();
    $this->putJson("/api/package-services/{$this->service->id}", [
        'business_id' => null,
    ])->assertForbidden();
    $this->deleteJson("/api/package-services/{$globalService->id}")->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum');
    $this->deleteJson("/api/package-services/{$globalService->id}")->assertOk();
    $this->assertDatabaseHas('package_services', ['id' => $globalService->id, 'is_active' => false]);
});
