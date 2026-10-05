<?php

use App\Models\BookingCalendarOverride;
use App\Models\BookingCalendarRule;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createCalendarBusinessUser(): array
{
    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şubesi',
        'slug' => 'test-subesi-' . Str::random(10),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $user = User::factory()->create();

    $businessUser = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $businessUser->branches()->attach(
        $branch->id,
        [
            'is_active' => true,
        ]
    );

    return [
        'business' => $business,
        'branch' => $branch,
        'user' => $user,
        'businessUser' => $businessUser,
    ];
}

test('authorized business user can view booking calendar', function () {
    $data = createCalendarBusinessUser();

    $response = $this->actingAs($data['user'])
        ->getJson(
            "/api/branches/{$data['branch']->id}/booking-calendar"
        );

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.branch_id',
            $data['branch']->id
        );
});

test('booking calendar returns open as default when no rule exists', function () {
    $data = createCalendarBusinessUser();

    $response = $this->actingAs($data['user'])
        ->getJson(
            "/api/branches/{$data['branch']->id}/booking-calendar"
        );

    $response->assertSuccessful()
        ->assertJsonPath(
            'data.default_status',
            'open'
        )
        ->assertJsonPath(
            'data.is_active',
            true
        );
});

test('business user can update default booking calendar rule', function () {
    $data = createCalendarBusinessUser();

    $response = $this->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$data['branch']->id}/booking-calendar",
            [
                'default_status' => 'closed',
                'is_active' => true,
            ]
        );

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.default_status',
            'closed'
        );

    $this->assertDatabaseHas(
        'booking_calendar_rules',
        [
            'branch_id' => $data['branch']->id,
            'default_status' => 'closed',
            'is_active' => true,
        ]
    );
});

test('business user can create a closed booking calendar override', function () {
    $data = createCalendarBusinessUser();

    $response = $this->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/booking-calendar/overrides",
            [
                'start_date' => '2027-08-15',
                'end_date' => '2027-08-15',
                'status' => 'closed',
                'reason' => 'Klinik tatili',
            ]
        );

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.status',
            'closed'
        );

    $this->assertDatabaseHas(
        'booking_calendar_overrides',
        [
            'branch_id' => $data['branch']->id,
            'start_date' => '2027-08-15 00:00:00',
            'end_date' => '2027-08-15 00:00:00',
            'status' => 'closed',
            'is_active' => true,
        ]
    );
});

test('business user can create an open booking calendar override', function () {
    $data = createCalendarBusinessUser();

    $response = $this->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/booking-calendar/overrides",
            [
                'start_date' => '2027-08-20',
                'end_date' => '2027-08-25',
                'status' => 'open',
                'reason' => 'Özel çalışma dönemi',
            ]
        );

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.status',
            'open'
        );

    $this->assertDatabaseHas(
        'booking_calendar_overrides',
        [
            'branch_id' => $data['branch']->id,
            'start_date' => '2027-08-20 00:00:00',
            'end_date' => '2027-08-25 00:00:00',
            'status' => 'open',
            'is_active' => true,
        ]
    );
});

test('business user cannot create override with invalid date range', function () {
    $data = createCalendarBusinessUser();

    $response = $this->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/booking-calendar/overrides",
            [
                'start_date' => '2027-08-20',
                'end_date' => '2027-08-19',
                'status' => 'closed',
            ]
        );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'end_date',
        ]);
});

test('business user can list booking calendar overrides', function () {
    $data = createCalendarBusinessUser();

    BookingCalendarOverride::create([
        'branch_id' => $data['branch']->id,
        'start_date' => '2027-08-10',
        'end_date' => '2027-08-12',
        'status' => 'closed',
        'reason' => 'Tatil',
        'is_active' => true,
    ]);

    BookingCalendarOverride::create([
        'branch_id' => $data['branch']->id,
        'start_date' => '2027-08-20',
        'end_date' => '2027-08-20',
        'status' => 'open',
        'reason' => 'Özel çalışma',
        'is_active' => true,
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson(
            "/api/branches/{$data['branch']->id}/booking-calendar/overrides"
        );

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(
            2,
            'data'
        );
});

test('business user can update a booking calendar override', function () {
    $data = createCalendarBusinessUser();

    $override = BookingCalendarOverride::create([
        'branch_id' => $data['branch']->id,
        'start_date' => '2027-08-10',
        'end_date' => '2027-08-10',
        'status' => 'closed',
        'reason' => 'Eski neden',
        'is_active' => true,
    ]);

    $response = $this->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$data['branch']->id}/booking-calendar/overrides/{$override->id}",
            [
                'start_date' => '2027-09-01',
                'end_date' => '2027-09-05',
                'status' => 'open',
                'reason' => 'Yeni neden',
                'is_active' => true,
            ]
        );

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.status',
            'open'
        );

    $this->assertDatabaseHas(
        'booking_calendar_overrides',
        [
            'id' => $override->id,
            'start_date' => '2027-09-01 00:00:00',
            'end_date' => '2027-09-05 00:00:00',
            'status' => 'open',
            'reason' => 'Yeni neden',
        ]
    );
});

test('business user can deactivate a booking calendar override', function () {
    $data = createCalendarBusinessUser();

    $override = BookingCalendarOverride::create([
        'branch_id' => $data['branch']->id,
        'start_date' => '2027-08-10',
        'end_date' => '2027-08-12',
        'status' => 'closed',
        'reason' => 'Tatil',
        'is_active' => true,
    ]);

    $response = $this->actingAs($data['user'])
        ->deleteJson(
            "/api/branches/{$data['branch']->id}/booking-calendar/overrides/{$override->id}"
        );

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas(
        'booking_calendar_overrides',
        [
            'id' => $override->id,
            'is_active' => false,
        ]
    );
});

test('business user cannot access another business branch calendar', function () {
    $data = createCalendarBusinessUser();

    $otherBusiness = Business::factory()->create();

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Başka İşletme Şubesi',
        'slug' => 'diger-subesi-' . Str::random(10),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson(
            "/api/branches/{$otherBranch->id}/booking-calendar"
        );

    $response->assertForbidden()
        ->assertJsonPath('success', false);
});

test('business user cannot update another business branch calendar', function () {
    $data = createCalendarBusinessUser();

    $otherBusiness = Business::factory()->create();

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Başka İşletme Şubesi',
        'slug' => 'diger-subesi-' . Str::random(10),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $response = $this->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$otherBranch->id}/booking-calendar",
            [
                'default_status' => 'closed',
                'is_active' => true,
            ]
        );

    $response->assertForbidden()
        ->assertJsonPath('success', false);
});

test('override belonging to another branch cannot be updated', function () {
    $data = createCalendarBusinessUser();

    $override = BookingCalendarOverride::create([
        'branch_id' => $data['branch']->id,
        'start_date' => '2027-08-10',
        'end_date' => '2027-08-10',
        'status' => 'closed',
        'reason' => 'Tatil',
        'is_active' => true,
    ]);

    $otherBusiness = Business::factory()->create();

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Başka Şube',
        'slug' => 'baska-sube-' . Str::random(10),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Sisli',
        'status' => 'active',
    ]);

    $response = $this->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$otherBranch->id}/booking-calendar/overrides/{$override->id}",
            [
                'start_date' => '2027-09-01',
                'end_date' => '2027-09-02',
                'status' => 'open',
                'reason' => 'Yetkisiz işlem',
                'is_active' => true,
            ]
        );

    $response->assertForbidden();

    $this->assertDatabaseHas(
        'booking_calendar_overrides',
        [
            'id' => $override->id,
            'branch_id' => $data['branch']->id,
            'status' => 'closed',
        ]
    );
});