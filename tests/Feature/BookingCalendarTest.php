<?php

use App\Models\BookingCalendarOverride;
use App\Models\BookingCalendarRule;
use App\Models\Branch;
use App\Models\Business;
use App\Services\BookingCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();

    $this->branch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'Test Şubesi',
        'slug' => 'test-subesi-' . uniqid(),
        'status' => 'active',
    ]);

    $this->service = app(BookingCalendarService::class);
});

test('branch is open by default when calendar rule is open', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'open',
        'is_active' => true,
    ]);

    $date = Carbon::create(2027, 8, 15);

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            $date
        )
    )->toBeTrue();
});

test('branch is closed by default when calendar rule is closed', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'closed',
        'is_active' => true,
    ]);

    $date = Carbon::create(2027, 8, 15);

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            $date
        )
    )->toBeFalse();
});

test('closed override closes an otherwise open date', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'open',
        'is_active' => true,
    ]);

    BookingCalendarOverride::create([
        'branch_id' => $this->branch->id,
        'start_date' => '2027-08-15',
        'end_date' => '2027-08-15',
        'status' => 'closed',
        'reason' => 'Tatil',
        'is_active' => true,
    ]);

    $date = Carbon::create(2027, 8, 15);

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            $date
        )
    )->toBeFalse();
});

test('open override opens an otherwise closed date', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'closed',
        'is_active' => true,
    ]);

    BookingCalendarOverride::create([
        'branch_id' => $this->branch->id,
        'start_date' => '2027-08-15',
        'end_date' => '2027-08-15',
        'status' => 'open',
        'reason' => 'Özel çalışma günü',
        'is_active' => true,
    ]);

    $date = Carbon::create(2027, 8, 15);

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            $date
        )
    )->toBeTrue();
});

test('date range override applies to every date in the range', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'open',
        'is_active' => true,
    ]);

    BookingCalendarOverride::create([
        'branch_id' => $this->branch->id,
        'start_date' => '2027-08-01',
        'end_date' => '2027-08-10',
        'status' => 'closed',
        'reason' => 'Klinik tatili',
        'is_active' => true,
    ]);

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            Carbon::create(2027, 8, 1)
        )
    )->toBeFalse();

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            Carbon::create(2027, 8, 5)
        )
    )->toBeFalse();

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            Carbon::create(2027, 8, 10)
        )
    )->toBeFalse();

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            Carbon::create(2027, 8, 11)
        )
    )->toBeTrue();
});

test('inactive override does not change the default calendar status', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'open',
        'is_active' => true,
    ]);

    BookingCalendarOverride::create([
        'branch_id' => $this->branch->id,
        'start_date' => '2027-08-15',
        'end_date' => '2027-08-15',
        'status' => 'closed',
        'reason' => 'Pasif kayıt',
        'is_active' => false,
    ]);

    $date = Carbon::create(2027, 8, 15);

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            $date
        )
    )->toBeTrue();
});

test('future dates have no maximum booking day restriction', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'open',
        'is_active' => true,
    ]);

    $futureDate = Carbon::create(2035, 12, 31);

    expect(
        $this->service->isDateOpen(
            $this->branch->id,
            $futureDate
        )
    )->toBeTrue();
});

test('open dates in range returns only dates that are open', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'open',
        'is_active' => true,
    ]);

    BookingCalendarOverride::create([
        'branch_id' => $this->branch->id,
        'start_date' => '2027-08-03',
        'end_date' => '2027-08-04',
        'status' => 'closed',
        'reason' => 'Kapalı gün',
        'is_active' => true,
    ]);

    $openDates = $this->service->getOpenDatesInRange(
        $this->branch->id,
        Carbon::create(2027, 8, 1),
        Carbon::create(2027, 8, 5)
    );

    expect($openDates)->toBe([
        '2027-08-01',
        '2027-08-02',
        '2027-08-05',
    ]);
});

test('has open date in range returns true when at least one date is open', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'closed',
        'is_active' => true,
    ]);

    BookingCalendarOverride::create([
        'branch_id' => $this->branch->id,
        'start_date' => '2027-08-05',
        'end_date' => '2027-08-05',
        'status' => 'open',
        'reason' => 'Özel açık gün',
        'is_active' => true,
    ]);

    expect(
        $this->service->hasOpenDateInRange(
            $this->branch->id,
            Carbon::create(2027, 8, 1),
            Carbon::create(2027, 8, 10)
        )
    )->toBeTrue();
});

test('has open date in range returns false when every date is closed', function () {
    BookingCalendarRule::create([
        'branch_id' => $this->branch->id,
        'default_status' => 'closed',
        'is_active' => true,
    ]);

    expect(
        $this->service->hasOpenDateInRange(
            $this->branch->id,
            Carbon::create(2027, 8, 1),
            Carbon::create(2027, 8, 10)
        )
    )->toBeFalse();
});