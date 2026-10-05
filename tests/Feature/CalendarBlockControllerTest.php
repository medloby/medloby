<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->otherBusiness = Business::factory()->create();

    $this->owner = User::factory()->create();
    $this->staff = User::factory()->create();

    $this->ownerMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $this->staffMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->branch = Branch::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Ana Sube',
        'slug' => 'ana-sube',
        'status' => 'active',
    ]);

    $this->otherBranch = Branch::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Ikinci Sube',
        'slug' => 'ikinci-sube',
        'status' => 'active',
    ]);

    $this->staffMembership->branches()->attach(
        $this->branch->id,
        [
            'is_active' => true,
        ]
    );

    $this->person = Person::factory()->create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
    ]);

    $this->doctor = Doctor::factory()->create([
        'person_id' => $this->person->id,
        'status' => 'active',
    ]);

    $this->doctor->branches()->attach(
        $this->branch->id,
        [
            'status' => 'active',
        ]
    );
});

test('business owner can list calendar blocks', function () {
    CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-10-10 09:00:00',
        'ends_at' => '2026-10-10 10:00:00',
        'block_type' => 'manual',
        'title' => 'Toplanti',
        'reason' => 'Planli toplantı',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches/{$this->branch->id}/calendar-blocks"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.blocks')
        ->assertJsonPath('data.blocks.0.block_type', 'manual');
});

test('staff can list calendar blocks for assigned branch', function () {
    CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->staff->id,
        'starts_at' => '2026-10-11 09:00:00',
        'ends_at' => '2026-10-11 11:00:00',
        'block_type' => 'manual',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->branch->id}/calendar-blocks"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.blocks');
});

test('business owner can create doctor calendar block', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'doctor_id' => $this->doctor->id,
                'starts_at' => '2026-10-20 09:00:00',
                'ends_at' => '2026-10-20 11:00:00',
                'block_type' => 'manual',
                'title' => 'Toplanti',
                'reason' => 'Planli toplantı',
                'is_active' => true,
            ]
        );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.doctor_id', $this->doctor->id)
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.block_type', 'manual')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('calendar_blocks', [
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'block_type' => 'manual',
        'title' => 'Toplanti',
        'is_active' => true,
    ]);
});

test('staff can create doctor calendar block for assigned branch', function () {
    $response = $this->actingAs($this->staff)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'doctor_id' => $this->doctor->id,
                'starts_at' => '2026-10-21 10:00:00',
                'ends_at' => '2026-10-21 12:00:00',
            ]
        );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true);
});

test('branch wide calendar block can be created without doctor', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'starts_at' => '2026-10-22 09:00:00',
                'ends_at' => '2026-10-22 12:00:00',
                'block_type' => 'maintenance',
                'title' => 'Bakim',
            ]
        );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.doctor_id', null);
});

test('end time before start time is rejected', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'starts_at' => '2026-10-23 17:00:00',
                'ends_at' => '2026-10-23 09:00:00',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'ends_at',
        ]);
});

test('overlapping doctor calendar block is rejected', function () {
    CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-10-24 09:00:00',
        'ends_at' => '2026-10-24 12:00:00',
        'block_type' => 'manual',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'doctor_id' => $this->doctor->id,
                'starts_at' => '2026-10-24 11:00:00',
                'ends_at' => '2026-10-24 13:00:00',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'starts_at',
        ]);
});

test('non overlapping doctor calendar block is allowed', function () {
    CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-10-25 09:00:00',
        'ends_at' => '2026-10-25 12:00:00',
        'block_type' => 'manual',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'doctor_id' => $this->doctor->id,
                'starts_at' => '2026-10-25 13:00:00',
                'ends_at' => '2026-10-25 15:00:00',
            ]
        );

    $response->assertCreated();
});

test('inactive calendar block does not block a new block', function () {
    CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-10-26 09:00:00',
        'ends_at' => '2026-10-26 12:00:00',
        'block_type' => 'manual',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'doctor_id' => $this->doctor->id,
                'starts_at' => '2026-10-26 09:00:00',
                'ends_at' => '2026-10-26 12:00:00',
            ]
        );

    $response->assertCreated();
});

test('business owner can update calendar block', function () {
    $block = CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-10-27 09:00:00',
        'ends_at' => '2026-10-27 11:00:00',
        'block_type' => 'manual',
        'title' => 'Eski baslik',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->putJson(
            "/api/branches/{$this->branch->id}/calendar-blocks/{$block->id}",
            [
                'starts_at' => '2026-10-27 13:00:00',
                'ends_at' => '2026-10-27 15:00:00',
                'title' => 'Yeni baslik',
            ]
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Yeni baslik');

    $this->assertDatabaseHas('calendar_blocks', [
        'id' => $block->id,
        'title' => 'Yeni baslik',
    ]);
});

test('business owner can deactivate calendar block', function () {
    $block = CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-10-28 09:00:00',
        'ends_at' => '2026-10-28 11:00:00',
        'block_type' => 'manual',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->deleteJson(
            "/api/branches/{$this->branch->id}/calendar-blocks/{$block->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('calendar_blocks', [
        'id' => $block->id,
        'is_active' => false,
    ]);
});

test('user from another business cannot access calendar blocks', function () {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->getJson(
            "/api/branches/{$this->branch->id}/calendar-blocks"
        );

    $response->assertForbidden();
});

test('staff cannot access an unassigned branch calendar blocks', function () {
    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->otherBranch->id}/calendar-blocks"
        );

    $response->assertForbidden();
});

test('doctor from another branch cannot receive calendar block', function () {
    $otherPerson = Person::factory()->create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
    ]);

    $otherDoctor = Doctor::factory()->create([
        'person_id' => $otherPerson->id,
        'status' => 'active',
    ]);

    $otherDoctor->branches()->attach(
        $this->otherBranch->id,
        [
            'status' => 'active',
        ]
    );

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'doctor_id' => $otherDoctor->id,
                'starts_at' => '2026-10-29 09:00:00',
                'ends_at' => '2026-10-29 11:00:00',
            ]
        );

    $response->assertUnprocessable();
});

test('inactive doctor branch relationship cannot receive calendar block', function () {
    $this->doctor->branches()->updateExistingPivot(
        $this->branch->id,
        [
            'status' => 'inactive',
        ]
    );

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/calendar-blocks",
            [
                'doctor_id' => $this->doctor->id,
                'starts_at' => '2026-10-30 09:00:00',
                'ends_at' => '2026-10-30 11:00:00',
            ]
        );

    $response->assertUnprocessable();
});

test('calendar block can be filtered by doctor', function () {
    CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-10-31 09:00:00',
        'ends_at' => '2026-10-31 11:00:00',
        'block_type' => 'manual',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches/{$this->branch->id}/calendar-blocks?doctor_id={$this->doctor->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.blocks');
});

test('inactive calendar blocks are not listed', function () {
    CalendarBlock::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'doctor_id' => $this->doctor->id,
        'created_by_user_id' => $this->owner->id,
        'starts_at' => '2026-11-01 09:00:00',
        'ends_at' => '2026-11-01 11:00:00',
        'block_type' => 'manual',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches/{$this->branch->id}/calendar-blocks"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data.blocks');
});