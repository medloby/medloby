<?php

use App\Models\Business;
use App\Models\ClinicContractAcceptance;
use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('clinic contract acceptance can be connected to business user and contract', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Medloby Klinik Üyelik Sözleşmesi',
        'version' => '1.0',
        'content' => 'Test sözleşme metni.',
        'status' => 'published',
        'is_required' => true,
        'effective_at' => now(),
        'published_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $acceptance = ClinicContractAcceptance::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'platform_contract_id' => $contract->id,
        'contract_version' => $contract->version,
        'accepted_at' => now(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Medloby Test Browser',
        'acceptance_method' => 'checkbox',
        'is_accepted' => true,
    ]);

    expect($acceptance->exists)->toBeTrue();

    $this->assertDatabaseHas('clinic_contract_acceptances', [
        'id' => $acceptance->id,
        'business_id' => $business->id,
        'user_id' => $user->id,
        'platform_contract_id' => $contract->id,
        'contract_version' => '1.0',
        'acceptance_method' => 'checkbox',
        'is_accepted' => true,
    ]);

    $acceptance->load([
        'business',
        'user',
        'platformContract',
    ]);

    expect($acceptance->business->id)->toBe($business->id);
    expect($acceptance->user->id)->toBe($user->id);
    expect($acceptance->platformContract->id)->toBe($contract->id);
    expect($acceptance->platformContract->version)->toBe('1.0');
});

test('business can access its clinic contract acceptances', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Medloby Klinik Üyelik Sözleşmesi',
        'version' => '1.0',
        'content' => 'Test sözleşme metni.',
        'status' => 'published',
        'is_required' => true,
        'effective_at' => now(),
        'published_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $acceptance = ClinicContractAcceptance::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'platform_contract_id' => $contract->id,
        'contract_version' => $contract->version,
        'accepted_at' => now(),
        'acceptance_method' => 'checkbox',
        'is_accepted' => true,
    ]);

    $business->load('contractAcceptances');

    expect($business->contractAcceptances)->toHaveCount(1);
    expect($business->contractAcceptances->first()->id)->toBe($acceptance->id);
});

test('user can access clinic contract acceptances they made', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Medloby Klinik Üyelik Sözleşmesi',
        'version' => '1.0',
        'content' => 'Test sözleşme metni.',
        'status' => 'published',
        'is_required' => true,
        'effective_at' => now(),
        'published_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    ClinicContractAcceptance::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'platform_contract_id' => $contract->id,
        'contract_version' => $contract->version,
        'accepted_at' => now(),
        'acceptance_method' => 'checkbox',
        'is_accepted' => true,
    ]);

    $user->load('contractAcceptances');

    expect($user->contractAcceptances)->toHaveCount(1);
    expect($user->contractAcceptances->first()->business_id)->toBe($business->id);
    expect($user->contractAcceptances->first()->platform_contract_id)->toBe($contract->id);
});