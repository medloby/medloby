<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\Permission;
use App\Models\Person;
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
        ->assertCreated()->assertJsonPath('data.slug', 'katalog-test-kategorisi');
    $categoryId = $category->json('data.id');

    $this->postJson('/api/treatments', [
        'treatment_category_id' => $categoryId,
        'name' => 'Katalog Test Tedavisi',
    ])->assertCreated()->assertJsonPath('data.slug', 'katalog-test-tedavisi');
});

test('generated catalog slugs return a validation error on duplicates and allow a distinct explicit slug', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum');
    $existing = createActiveCatalogTreatment();

    $this->postJson('/api/treatment-categories', ['name' => $existing->treatmentCategory->name])
        ->assertUnprocessable()->assertJsonValidationErrors('slug');

    $treatment = ['name' => $existing->name, 'treatment_category_id' => $existing->treatment_category_id];
    $this->postJson('/api/treatments', $treatment)->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->assertDatabaseCount('treatment_categories', 1);
    $this->assertDatabaseCount('treatments', 1);
    $this->postJson('/api/treatments', [...$treatment, 'slug' => 'ayri-test-tedavisi'])
        ->assertCreated()->assertJsonPath('data.slug', 'ayri-test-tedavisi');
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
    $crmPrices = "/api/branches/{$assignedBranch->id}/crm/treatment-prices";
    $client->getJson($crmPrices)->assertOk()->assertJsonPath('data.can_manage', true)
        ->assertJsonPath('data.prices.0.price', '120.00');
    $client->getJson($crmPrices.'?scope=clinic')->assertOk()->assertJsonPath('data.can_manage', false);
    $client->getJson("/api/branches/{$otherBranch->id}/crm/treatment-prices")->assertForbidden();
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

    $client = $this->actingAs($user, 'sanctum');
    $client
        ->postJson("/api/branches/{$branch->id}/treatments", ['treatment_id' => $treatment->id])
        ->assertForbidden();
    $client->getJson("/api/branches/{$branch->id}/crm/treatment-prices")->assertOk()
        ->assertJsonPath('data.can_manage', false)->assertJsonPath('data.treatments', [])->assertJsonPath('data.doctors', []);

    $this->assertDatabaseMissing('branch_treatment', ['branch_id' => $branch->id, 'treatment_id' => $treatment->id]);
});

test('the clinic price workspace keeps branch prices separate from clinic prices and limits editor choices', function () {
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $otherBranch = Branch::factory()->for($business)->create();
    $owner = User::factory()->create();
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);

    $treatment = createActiveCatalogTreatment();
    $inactiveLink = Treatment::create(['treatment_category_id' => $treatment->treatment_category_id,
        'name' => 'Inactive branch link', 'slug' => 'inactive-link', 'is_active' => true]);
    $branch->treatments()->attach($treatment, ['is_active' => true]);
    $branch->treatments()->attach($inactiveLink, ['is_active' => false]);
    $doctor = Doctor::factory()->for(Person::factory()->for($business)->for($branch))->create();
    $otherDoctor = Doctor::factory()->for(Person::factory()->for($business)->for($otherBranch))->create();
    $doctor->branches()->attach($branch, ['status' => 'active']);
    $otherDoctor->branches()->attach($otherBranch, ['status' => 'active']);

    $priceData = ['business_id' => $business->id, 'treatment_id' => $treatment->id, 'price_type' => 'fixed', 'currency' => 'TRY', 'price' => 750];
    $clinicPrice = TreatmentPrice::create($priceData);
    $branchPrice = TreatmentPrice::create($priceData + ['branch_id' => $branch->id, 'doctor_id' => $doctor->id, 'is_active' => false]);
    TreatmentPrice::create($priceData + ['branch_id' => $otherBranch->id]);
    $path = "/api/branches/{$branch->id}/crm/treatment-prices";

    $this->getJson($path)->assertUnauthorized();
    $client = $this->actingAs($owner, 'sanctum');
    $client->getJson($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.can_manage', true)
        ->assertJsonCount(1, 'data.prices')->assertJsonPath('data.prices.0.id', $branchPrice->id)
        ->assertJsonPath('data.prices.0.is_active', false)->assertJsonPath('data.prices.0.doctor.person.first_name', $doctor->person->first_name)
        ->assertJsonMissingPath('data.prices.0.doctor.license_number')->assertJsonMissingPath('data.prices.0.doctor.person.email')
        ->assertJsonPath('data.treatments', [['id' => $treatment->id, 'name' => $treatment->name]])
        ->assertJsonCount(1, 'data.doctors')->assertJsonPath('data.doctors.0.id', $doctor->id);
    $client->getJson($path.'?scope=clinic')->assertOk()->assertJsonPath('data.can_manage', true)
        ->assertJsonCount(1, 'data.prices')->assertJsonPath('data.prices.0.id', $clinicPrice->id)
        ->assertJsonCount(2, 'data.doctors')->assertJsonCount(2, 'data.treatments');
    $client->getJson($path.'?scope=invalid')->assertUnprocessable()->assertJsonValidationErrors('scope');
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
