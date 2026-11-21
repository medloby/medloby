<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Permission;
use App\Models\SocialMediaAccount;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createActiveCatalogTreatment(): Treatment
{
    $category = TreatmentCategory::factory()->create(['is_active' => true]);

    return Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Test Tedavisi',
        'slug' => 'test-tedavisi',
        'is_active' => true,
    ]);
}

test('catalog write routes require an authenticated and verified user', function () {
    $branch = Branch::factory()->create();
    $package = $branch->business->treatmentPackages()->create([
        'branch_id' => $branch->id,
        'name' => 'Test Paketi',
        'slug' => 'test-paketi',
    ]);
    $paths = [
        '/api/treatment-categories',
        '/api/treatments',
        "/api/branches/{$branch->id}/treatments",
        "/api/branches/{$branch->id}/social-media",
        '/api/treatment-prices',
        '/api/treatment-packages',
        "/api/treatment-packages/{$package->id}/items",
        '/api/package-services',
        "/api/treatment-packages/{$package->id}/service-items",
    ];

    foreach ($paths as $path) {
        $this->postJson($path)->assertUnauthorized();
    }

    $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]), 'sanctum');
    foreach ($paths as $path) {
        $this->postJson($path)->assertForbidden();
    }
});

test('global category and treatment definitions require a platform administrator', function () {
    $category = TreatmentCategory::factory()->create();
    $owner = User::factory()->create();
    Business::factory()->create()->memberships()->create([
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $client = $this->actingAs($owner, 'sanctum');
    $client->putJson("/api/treatment-categories/{$category->id}", ['name' => 'Yetkisiz kategori'])->assertForbidden();
    $client->postJson('/api/treatments', [
        'treatment_category_id' => $category->id,
        'name' => 'Yetkisiz tedavi',
    ])->assertForbidden();

    expect($category->fresh()->name)->toBe($category->name);
    $this->assertDatabaseMissing('treatments', ['name' => 'Yetkisiz tedavi']);
});

test('a verified platform administrator can manage global catalog definitions using a bearer token', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $token = $admin->createToken('catalog-test')->plainTextToken;

    $category = $this->withToken($token)
        ->postJson('/api/treatment-categories', ['name' => 'Katalog Test Kategorisi', 'is_active' => true])
        ->assertCreated();
    $categoryId = $category->json('data.id');

    $this->postJson('/api/treatments', [
        'treatment_category_id' => $categoryId,
        'name' => 'Katalog Test Tedavisi',
    ])->assertCreated();
});

test('assigned users with branch management permission can manage the assigned branch catalog', function () {
    $business = Business::factory()->create();
    $assignedBranch = Branch::factory()->for($business)->create();
    $otherBranch = Branch::factory()->for($business)->create();
    $otherBusinessBranch = Branch::factory()->create();
    $treatment = createActiveCatalogTreatment();
    $manager = User::factory()->create();
    $membership = $business->memberships()->create([
        'user_id' => $manager->id,
        'role' => 'manager',
        'is_active' => true,
    ]);
    $membership->branches()->attach($assignedBranch->id, ['is_active' => true]);
    $permission = Permission::create([
        'name' => 'branches.manage',
        'display_name' => 'Şube yönetimi',
        'module' => 'branches',
        'is_active' => true,
    ]);
    $manager->permissions()->attach($permission->id, ['business_id' => $business->id, 'is_allowed' => true]);

    $client = $this->actingAs($manager, 'sanctum');
    $client->postJson("/api/branches/{$assignedBranch->id}/treatments", [
        'treatment_id' => $treatment->id,
    ])->assertCreated();

    $socialAccount = $client->postJson("/api/branches/{$assignedBranch->id}/social-media", [
        'platform' => 'instagram',
        'profile_url' => 'https://instagram.com/catalog-test',
    ])->assertCreated();
    $accountId = $socialAccount->json('data.id');

    $priceFields = [
        'business_id' => $business->id,
        'treatment_id' => $treatment->id,
        'price_type' => 'fixed',
        'currency' => 'TRY',
        'price' => 100,
    ];
    $priceId = $client->postJson('/api/treatment-prices', $priceFields + ['branch_id' => $assignedBranch->id])
        ->assertCreated()->json('data.id');

    $client->putJson("/api/treatment-prices/{$priceId}", ['price' => 120])->assertOk();
    $client->postJson('/api/treatment-prices', $priceFields)->assertForbidden();
    $client->putJson("/api/treatment-prices/{$priceId}", ['branch_id' => $otherBranch->id])->assertForbidden();
    $client->deleteJson("/api/branches/{$otherBranch->id}/treatments/{$treatment->id}")->assertForbidden();
    $client->putJson("/api/branches/{$otherBusinessBranch->id}/social-media/{$accountId}", ['is_active' => false])->assertForbidden();

    $this->assertDatabaseHas('treatment_prices', ['id' => $priceId, 'branch_id' => $assignedBranch->id, 'price' => 120]);
    expect(SocialMediaAccount::findOrFail($accountId)->is_active)->toBeTrue();
});

test('branch assignment without management permission does not allow catalog writes', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    $member = $branch->business->memberships()->create([
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);
    $member->branches()->attach($branch->id, ['is_active' => true]);
    $treatment = createActiveCatalogTreatment();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/branches/{$branch->id}/treatments", ['treatment_id' => $treatment->id])
        ->assertForbidden();

    $this->assertDatabaseMissing('branch_treatment', ['branch_id' => $branch->id, 'treatment_id' => $treatment->id]);
});

test('a price scope change requires management permission in both businesses', function () {
    $originalBusiness = Business::factory()->create();
    $destinationBusiness = Business::factory()->create();
    $owner = User::factory()->create();
    $destinationBusiness->memberships()->create([
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);
    $price = TreatmentPrice::create([
        'business_id' => $originalBusiness->id,
        'treatment_id' => createActiveCatalogTreatment()->id,
        'price_type' => 'fixed',
        'price' => 100,
        'currency' => 'TRY',
    ]);
    $path = "/api/treatment-prices/{$price->id}";

    $client = $this->actingAs($owner, 'sanctum');
    $client->putJson($path, ['business_id' => $destinationBusiness->id])->assertForbidden();
    expect($price->fresh()->business_id)->toBe($originalBusiness->id);

    $originalBusiness->memberships()->create([
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);
    $client->putJson($path, ['business_id' => $destinationBusiness->id])
        ->assertOk()
        ->assertJsonPath('data.business_id', $destinationBusiness->id);
});
