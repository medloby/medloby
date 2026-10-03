<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUserBranchAccessBusiness(): Business
{
    return Business::factory()->create();
}

function createUserBranchAccessUser(
    Business $business,
    string $role = 'staff',
    bool $isActive = true
): array {
    $user = User::factory()->create();

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => $role,
        'is_active' => $isActive,
    ]);

    return [
        'user' => $user,
        'membership' => $membership,
    ];
}

function createUserBranchAccessBranch(
    Business $business,
    string $name,
    string $status = 'active'
): Branch {
    return Branch::create([
        'business_id' => $business->id,
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        'status' => $status,
    ]);
}

test('business owner can access every branch of their business', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'business_owner'
    );

    $branchA = createUserBranchAccessBranch(
        $business,
        'Owner Şube A'
    );

    $branchB = createUserBranchAccessBranch(
        $business,
        'Owner Şube B'
    );

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $branchA->id
        )
    )->toBeTrue();

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $branchB->id
        )
    )->toBeTrue();
});

test('manager can access only assigned active branches', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'manager'
    );

    $assignedBranch = createUserBranchAccessBranch(
        $business,
        'Manager Yetkili Şube'
    );

    $unassignedBranch = createUserBranchAccessBranch(
        $business,
        'Manager Yetkisiz Şube'
    );

    $data['membership']->branches()->attach(
        $assignedBranch->id,
        [
            'is_active' => true,
        ]
    );

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $assignedBranch->id
        )
    )->toBeTrue();

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $unassignedBranch->id
        )
    )->toBeFalse();
});

test('staff can access only assigned active branches', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'staff'
    );

    $assignedBranch = createUserBranchAccessBranch(
        $business,
        'Personel Yetkili Şube'
    );

    $unassignedBranch = createUserBranchAccessBranch(
        $business,
        'Personel Yetkisiz Şube'
    );

    $data['membership']->branches()->attach(
        $assignedBranch->id,
        [
            'is_active' => true,
        ]
    );

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $assignedBranch->id
        )
    )->toBeTrue();

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $unassignedBranch->id
        )
    )->toBeFalse();
});

test('inactive branch assignment cannot provide branch access', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'staff'
    );

    $branch = createUserBranchAccessBranch(
        $business,
        'Pasif Yetki Şubesi'
    );

    $data['membership']->branches()->attach(
        $branch->id,
        [
            'is_active' => false,
        ]
    );

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $branch->id
        )
    )->toBeFalse();
});

test('inactive business membership cannot access any branch', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'business_owner',
        false
    );

    $branch = createUserBranchAccessBranch(
        $business,
        'Pasif Üyelik Şubesi'
    );

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            $branch->id
        )
    )->toBeFalse();
});

test('user cannot access branch belonging to another business', function () {
    $businessA = createUserBranchAccessBusiness();
    $businessB = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $businessA,
        'business_owner'
    );

    $branchB = createUserBranchAccessBranch(
        $businessB,
        'Başka İşletmenin Şubesi'
    );

    expect(
        $data['user']->hasBusinessBranchAccess(
            $businessA->id,
            $branchB->id
        )
    )->toBeFalse();
});

test('user cannot access nonexistent branch', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'business_owner'
    );

    expect(
        $data['user']->hasBusinessBranchAccess(
            $business->id,
            999999
        )
    )->toBeFalse();
});

test('accessible branch ids returns every branch for business owner', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'business_owner'
    );

    $branchA = createUserBranchAccessBranch(
        $business,
        'Owner Liste Şube A'
    );

    $branchB = createUserBranchAccessBranch(
        $business,
        'Owner Liste Şube B'
    );

    $accessibleBranchIds = $data['user']->accessibleBranchIds(
        $business->id
    );

    expect($accessibleBranchIds)
        ->toContain($branchA->id)
        ->toContain($branchB->id);
});

test('accessible branch ids returns only assigned active branches for staff', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'staff'
    );

    $assignedBranch = createUserBranchAccessBranch(
        $business,
        'Staff Liste Yetkili'
    );

    $unassignedBranch = createUserBranchAccessBranch(
        $business,
        'Staff Liste Yetkisiz'
    );

    $inactiveBranch = createUserBranchAccessBranch(
        $business,
        'Staff Liste Pasif',
        'inactive'
    );

    $data['membership']->branches()->attach([
        $assignedBranch->id => [
            'is_active' => true,
        ],
        $inactiveBranch->id => [
            'is_active' => true,
        ],
    ]);

    $accessibleBranchIds = $data['user']->accessibleBranchIds(
        $business->id
    );

    expect($accessibleBranchIds)
        ->toContain($assignedBranch->id)
        ->not->toContain($unassignedBranch->id)
        ->not->toContain($inactiveBranch->id);
});

test('accessible branch ids returns empty array for inactive membership', function () {
    $business = createUserBranchAccessBusiness();

    $data = createUserBranchAccessUser(
        $business,
        'manager',
        false
    );

    $branch = createUserBranchAccessBranch(
        $business,
        'Pasif Üyelik Liste Şubesi'
    );

    $data['membership']->branches()->attach(
        $branch->id,
        [
            'is_active' => true,
        ]
    );

    expect(
        $data['user']->accessibleBranchIds($business->id)
    )->toBe([]);
});