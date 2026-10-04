<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('doctor availability service rejects time outside working hours', function () {
    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Availability Test Branch',
        'slug' => 'availability-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Availability',
        'last_name' => 'Doctor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'AVAIL-' . uniqid(),
        'specialty' => 'Test',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Availability Category',
        'slug' => 'availability-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Availability Treatment',
        'slug' => 'availability-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branch->id,
        'treatment_id' => $treatment->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
        'is_offer_enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('doctor_treatment')->insert([
        'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id,
        'duration_minutes' => 60,
        'is_active' => true,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = now()->addDays(7)->startOfDay();

    DoctorWorkingHour::create([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $service = app(AppointmentAvailabilityService::class);

    $outsideWorkingHours = $date->copy()->setTime(18, 30);

    expect(
        $service->isAvailable(
            $doctor,
            $branch->id,
            $treatment,
            $outsideWorkingHours
        )
    )->toBeFalse();
});

test('doctor availability rejects appointment when treatment duration exceeds working hours', function () {
    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Duration Test Branch',
        'slug' => 'duration-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Duration',
        'last_name' => 'Doctor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'DURATION-' . uniqid(),
        'specialty' => 'Test',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Duration Category',
        'slug' => 'duration-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Duration Treatment',
        'slug' => 'duration-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branch->id,
        'treatment_id' => $treatment->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
        'is_offer_enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('doctor_treatment')->insert([
        'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id,
        'duration_minutes' => 60,
        'is_active' => true,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = now()->addDays(7)->startOfDay();

    DoctorWorkingHour::create([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $service = app(AppointmentAvailabilityService::class);

    $startsAt = $date->copy()->setTime(17, 30);

    expect(
        $service->isAvailable(
            $doctor,
            $branch->id,
            $treatment,
            $startsAt
        )
    )->toBeFalse();
});

test('authenticated business user can retrieve appointment availability', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $businessUser = BusinessUser::create([
    'business_id' => $business->id,
    'user_id' => $user->id,
    'role' => 'owner',
    'is_active' => true,
]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'API Availability Branch',
        'slug' => 'api-availability-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $businessUser->branches()->attach($branch->id, [
    'is_active' => true,
]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'API',
        'last_name' => 'Doctor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'API-' . uniqid(),
        'specialty' => 'Test',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'API Availability Category',
        'slug' => 'api-availability-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'API Availability Treatment',
        'slug' => 'api-availability-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branch->id,
        'treatment_id' => $treatment->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
        'is_offer_enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('doctor_treatment')->insert([
        'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id,
        'duration_minutes' => 60,
        'is_active' => true,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = Carbon::parse('2026-10-12')->startOfDay();

    DoctorWorkingHour::create([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson('/api/appointments/availability?' . http_build_query([
            'branch_id' => $branch->id,
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'date' => $date->toDateString(),
            'slot_interval' => 30,
            'require_online_bookable' => true,
        ]));

    $response->assertOk();

    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.date', '2026-10-12');
    $response->assertJsonPath('data.branch_id', $branch->id);
    $response->assertJsonPath('data.doctor_id', $doctor->id);
    $response->assertJsonPath('data.treatment_id', $treatment->id);
    $response->assertJsonPath('data.slot_interval', 30);

    $slots = $response->json('data.slots');

    expect($slots)->not->toBeEmpty();
    expect($slots[0])->toBe('2026-10-12 09:00:00');
    expect($slots[array_key_last($slots)])
        ->toBe('2026-10-12 17:00:00');
});