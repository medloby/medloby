<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('business user can be assigned to multiple branches of the same business', function () {
    $business = Business::factory()->create();

    $user = User::factory()->create();

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $branchA = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şube A',
        'slug' => 'test-sube-a-' . uniqid(),
        'status' => 'active',
    ]);

    $branchB = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şube B',
        'slug' => 'test-sube-b-' . uniqid(),
        'status' => 'active',
    ]);

    $membership->branches()->attach([
        $branchA->id => [
            'is_active' => true,
        ],
        $branchB->id => [
            'is_active' => true,
        ],
    ]);

    expect($membership->branches()->count())
        ->toBe(2);

    expect($membership->branches->pluck('id')->all())
        ->toContain($branchA->id)
        ->toContain($branchB->id);
});

test('business user branch relationship keeps branch assignments isolated by business', function () {
    $businessA = Business::factory()->create();

    $businessB = Business::factory()->create();

    $user = User::factory()->create();

    $membership = BusinessUser::create([
        'business_id' => $businessA->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $branchA = Branch::create([
        'business_id' => $businessA->id,
        'name' => 'İşletme A Şubesi',
        'slug' => 'isletme-a-subesi-' . uniqid(),
        'status' => 'active',
    ]);

    $branchB = Branch::create([
        'business_id' => $businessB->id,
        'name' => 'İşletme B Şubesi',
        'slug' => 'isletme-b-subesi-' . uniqid(),
        'status' => 'active',
    ]);

    $membership->branches()->attach([
        $branchA->id => [
            'is_active' => true,
        ],
    ]);

    expect($membership->branches()->count())
        ->toBe(1);

    expect($membership->branches->first()->id)
        ->toBe($branchA->id);

    expect($membership->branches->contains($branchB->id))
        ->toBeFalse();
});

test('business user branch assignment can be deactivated without removing the relationship', function () {
    $business = Business::factory()->create();

    $user = User::factory()->create();

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şube',
        'slug' => 'test-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $membership->branches()->attach($branch->id, [
        'is_active' => true,
    ]);

    $membership->branches()->updateExistingPivot(
        $branch->id,
        [
            'is_active' => false,
        ]
    );

    $membership->refresh();

    expect($membership->branches()->count())
        ->toBe(1);

    expect(
        (bool) $membership->branches()->first()->pivot->is_active
    )->toBeFalse();
});

test('branch can retrieve its assigned business users', function () {
    $business = Business::factory()->create();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $membershipA = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $userA->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $membershipB = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $userB->id,
        'role' => 'manager',
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Ortak Şube',
        'slug' => 'ortak-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $membershipA->branches()->attach($branch->id, [
        'is_active' => true,
    ]);

    $membershipB->branches()->attach($branch->id, [
        'is_active' => true,
    ]);

    $businessUsers = $branch->businessUsers()->get();

    expect($businessUsers->count())
        ->toBe(2);

    expect($businessUsers->pluck('id')->all())
        ->toContain($membershipA->id)
        ->toContain($membershipB->id);
});

test('duplicate business user branch assignment is prevented', function () {
    $business = Business::factory()->create();

    $user = User::factory()->create();

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şube',
        'slug' => 'test-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $membership->branches()->attach($branch->id, [
        'is_active' => true,
    ]);

    expect(fn () => $membership->branches()->attach($branch->id, [
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});