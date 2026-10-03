<?php

use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('business review can be connected to business and admin user', function () {
    $admin = User::factory()->create();

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $review = BusinessReview::create([
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'approved',
        'reviewed_at' => now(),
        'notes' => 'Klinik başvurusu incelendi.',
        'rejection_reason' => null,
        'previous_status' => 'pending',
        'new_status' => 'active',
    ]);

    expect($review->exists)->toBeTrue();

    $this->assertDatabaseHas('business_reviews', [
        'id' => $review->id,
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'approved',
        'previous_status' => 'pending',
        'new_status' => 'active',
    ]);

    $review->load([
        'business',
        'reviewedBy',
    ]);

    expect($review->business->id)->toBe($business->id);
    expect($review->reviewedBy->id)->toBe($admin->id);
    expect($review->reviewed_at)->not->toBeNull();
});

test('business can access its review history', function () {
    $admin = User::factory()->create();

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $review = BusinessReview::create([
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'rejected',
        'reviewed_at' => now(),
        'notes' => 'Eksik bilgi bulundu.',
        'rejection_reason' => 'Klinik belgeleri eksik.',
        'previous_status' => 'pending',
        'new_status' => 'rejected',
    ]);

    $business->load('reviews');

    expect($business->reviews)->toHaveCount(1);
    expect($business->reviews->first()->id)->toBe($review->id);
    expect($business->reviews->first()->decision)->toBe('rejected');
    expect($business->reviews->first()->rejection_reason)
        ->toBe('Klinik belgeleri eksik.');
});

test('admin user can access business reviews they made', function () {
    $admin = User::factory()->create();

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $review = BusinessReview::create([
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'approved',
        'reviewed_at' => now(),
        'notes' => 'Başvuru onaylandı.',
        'rejection_reason' => null,
        'previous_status' => 'pending',
        'new_status' => 'active',
    ]);

    $admin->load('reviews');

    expect($admin->reviews)->toHaveCount(1);
    expect($admin->reviews->first()->id)->toBe($review->id);
    expect($admin->reviews->first()->business_id)->toBe($business->id);
});

test('business review can record rejection reason and status transition', function () {
    $admin = User::factory()->create();

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $review = BusinessReview::create([
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'rejected',
        'reviewed_at' => now(),
        'notes' => 'Başvuru reddedildi.',
        'rejection_reason' => 'Gerekli belgeler sunulmadı.',
        'previous_status' => 'pending',
        'new_status' => 'rejected',
    ]);

    expect($review->decision)->toBe('rejected');
    expect($review->previous_status)->toBe('pending');
    expect($review->new_status)->toBe('rejected');
    expect($review->rejection_reason)
        ->toBe('Gerekli belgeler sunulmadı.');

    $this->assertDatabaseHas('business_reviews', [
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'rejected',
        'previous_status' => 'pending',
        'new_status' => 'rejected',
    ]);
});