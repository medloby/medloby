<?php

use App\Models\Business;
use App\Models\BusinessStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('business can access its status history', function () {
    $business = Business::factory()->create([
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $history = BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $user->id,
        'previous_status' => 'pending',
        'new_status' => 'active',
        'reason' => 'Klinik başvurusu onaylandı.',
        'changed_at' => now(),
    ]);

    $business->load('statusHistories');

    expect($business->statusHistories)
        ->toHaveCount(1);

    expect($business->statusHistories->first()->id)
        ->toBe($history->id);

    expect($business->statusHistories->first()->new_status)
        ->toBe('active');
});

test('user can access business status changes they made', function () {
    $business = Business::factory()->create();

    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $history = BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'active',
        'new_status' => 'suspended',
        'reason' => 'Platform kurallarına aykırı işlem.',
        'changed_at' => now(),
    ]);

    $admin->load('businessStatusChanges');

    expect($admin->businessStatusChanges)
        ->toHaveCount(1);

    expect($admin->businessStatusChanges->first()->id)
        ->toBe($history->id);
});

test('business status history can access its business and changed by user', function () {
    $business = Business::factory()->create();

    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $history = BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'active',
        'new_status' => 'suspended',
        'reason' => 'Test sebebi.',
        'changed_at' => now(),
    ]);

    $history->load([
        'business',
        'changedBy',
    ]);

    expect($history->business->id)
        ->toBe($business->id);

    expect($history->changedBy->id)
        ->toBe($admin->id);
});

test('business status history stores status transition and reason', function () {
    $business = Business::factory()->create();

    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'active',
        'new_status' => 'suspended',
        'reason' => 'Geçici olarak askıya alındı.',
        'changed_at' => now(),
    ]);

    $this->assertDatabaseHas('business_status_histories', [
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'active',
        'new_status' => 'suspended',
        'reason' => 'Geçici olarak askıya alındı.',
    ]);
});

test('business status history casts changed at as datetime', function () {
    $business = Business::factory()->create();

    $history = BusinessStatusHistory::create([
        'business_id' => $business->id,
        'previous_status' => 'pending',
        'new_status' => 'active',
        'changed_at' => '2026-10-03 13:30:00',
    ]);

    expect($history->changed_at)
        ->toBeInstanceOf(\Illuminate\Support\Carbon::class);

    expect($history->changed_at->format('Y-m-d H:i:s'))
        ->toBe('2026-10-03 13:30:00');
});