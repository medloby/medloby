<?php

use App\Models\Offer;
use App\Models\Conversation;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('patient can create appointment through booking service', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Booking Test Şubesi',
        'slug' => 'booking-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'BOOK-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Booking Test Kategorisi',
        'slug' => 'booking-test-kategorisi-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Booking Test Tedavisi',
        'slug' => 'booking-test-tedavisi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $appointment = app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
    );

    expect($appointment->business_id)
        ->toBe($business->id);

    expect($appointment->branch_id)
        ->toBe($branch->id);

    expect($appointment->patient_profile_id)
        ->toBe($patient->id);

    expect($appointment->doctor_id)
        ->toBe($doctor->id);

    expect($appointment->treatment_id)
        ->toBe($treatment->id);

    expect($appointment->status)
        ->toBe('pending');

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id,
        'status' => 'pending',
    ]);
});

test('booking cannot use a branch from another business', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Başka İşletme Şubesi',
        'slug' => 'diger-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'BOOK-OTHER-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Booking Güvenlik Kategorisi',
        'slug' => 'booking-guvenlik-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Booking Güvenlik Tedavisi',
        'slug' => 'booking-guvenlik-tedavisi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
    ))->toThrow(
        RuntimeException::class,
        'Seçilen şube bu işletmeye ait değil.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('booking cannot use a doctor from another business', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Ana İşletme Şubesi',
        'slug' => 'ana-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer İşletme Şubesi',
        'slug' => 'diger-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranch->id,
        'first_name' => 'Başka',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'BOOK-DOCTOR-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Doktor Güvenlik Kategorisi',
        'slug' => 'doktor-guvenlik-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Doktor Güvenlik Tedavisi',
        'slug' => 'doktor-guvenlik-tedavisi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $otherBranch->id,
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $otherBranch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
    ))->toThrow(
        RuntimeException::class,
        'Seçilen doktor bu işletmeye ait değil.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('booking cannot use a doctor who does not perform treatment', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Tedavi Test Şubesi',
        'slug' => 'tedavi-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'BOOK-TREATMENT-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Tedavi Yetki Kategorisi',
        'slug' => 'tedavi-yetki-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Doktorun Yapmadığı Tedavi',
        'slug' => 'doktorun-yapmadigi-tedavi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    // Bilerek doctor_treatment kaydı oluşturmuyoruz.
    // Doktor bu tedaviyi yapmıyor.

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
    ))->toThrow(
        RuntimeException::class,
        'Seçilen tarih ve saat için randevu müsait değil.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('booking cannot be created when treatment is not online bookable', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Online Booking Test Şubesi',
        'slug' => 'online-booking-test-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'BOOK-ONLINE-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Online Booking Kategorisi',
        'slug' => 'online-booking-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Online Randevu Kapalı Tedavi',
        'slug' => 'online-kapali-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => false,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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
        'is_online_bookable' => false,
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
    ))->toThrow(
        RuntimeException::class,
        'Seçilen tedavi bu şubede online randevuya açık değil.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('accepted offer can be converted into an appointment', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Teklif Booking Şubesi',
        'slug' => 'teklif-booking-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Teklif',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OFFER-BOOK-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Teklif Booking Kategorisi',
        'slug' => 'teklif-booking-kategori-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Teklif Booking Tedavisi',
        'slug' => 'teklif-booking-tedavisi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Teklif',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

$conversation = Conversation::create([
    'business_id' => $business->id,
    'branch_id' => $branch->id,
    'patient_profile_id' => $patient->id,
    'subject' => 'Test Randevu Teklifi',
    'status' => 'open',
]);
    
    $offer = Offer::create([
    'conversation_id' => $conversation->id,    
    'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Test Teklif',
        'description' => 'Booking test teklifi',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'accepted',
        'responded_at' => now(),
        'treatment_id' => $treatment->id,
    ]);

    $appointment = app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
        offer: $offer,
    );

    expect($appointment->offer_id)
        ->toBe($offer->id);

    expect($appointment->status)
        ->toBe('pending');

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'offer_id' => $offer->id,
        'patient_profile_id' => $patient->id,
        'treatment_id' => $treatment->id,
    ]);
});

test('accepted offer cannot be converted into an appointment twice', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Teklif Tekrar Test Şubesi',
        'slug' => 'teklif-tekrar-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Tekrar',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OFFER-REPEAT-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Tekrar Teklif Kategorisi',
        'slug' => 'tekrar-teklif-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Tekrar Teklif Tedavisi',
        'slug' => 'tekrar-teklif-tedavisi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Tekrar',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Tekrar Randevu Teklifi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Tekrar Test Teklifi',
        'description' => 'Aynı teklif iki kez kullanılmamalı.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'accepted',
        'responded_at' => now(),
        'treatment_id' => $treatment->id,
    ]);

    $service = app(AppointmentBookingService::class);

    $firstAppointment = $service->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
        offer: $offer,
    );

    expect($firstAppointment->offer_id)
        ->toBe($offer->id);

    expect(fn () => $service->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt->copy()->addHours(2)),
        offer: $offer,
    ))->toThrow(
        RuntimeException::class,
        'Bu teklif daha önce randevuya dönüştürülmüş.'
    );

    $this->assertDatabaseCount('appointments', 1);
});

test('expired offer cannot be converted into an appointment', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Süresi Dolmuş Teklif Şubesi',
        'slug' => 'expired-offer-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Süre',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OFFER-EXPIRED-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Süresi Dolmuş Teklif Kategorisi',
        'slug' => 'expired-offer-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Süresi Dolmuş Teklif Tedavisi',
        'slug' => 'expired-offer-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Süre',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Süresi Dolmuş Teklif',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Süresi Dolmuş Test Teklifi',
        'description' => 'Geçerlilik tarihi geçmiş teklif.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->subDay(),
        'status' => 'accepted',
        'responded_at' => now()->subDays(2),
        'treatment_id' => $treatment->id,
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
        offer: $offer,
    ))->toThrow(
        RuntimeException::class,
        'Bu teklifin geçerlilik süresi dolmuştur.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('booking cannot use an offer belonging to another business', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Ana İşletme Şubesi',
        'slug' => 'ana-isletme-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer İşletme Şubesi',
        'slug' => 'diger-isletme-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OFFER-BUSINESS-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'İşletme Teklif Kategorisi',
        'slug' => 'business-offer-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'İşletme Teklif Tedavisi',
        'slug' => 'business-offer-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Başka İşletme Teklifi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Başka İşletme Teklifi',
        'description' => 'Başka işletmeye ait teklif.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'accepted',
        'responded_at' => now(),
        'treatment_id' => $treatment->id,
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
        offer: $offer,
    ))->toThrow(
        RuntimeException::class,
        'Teklif farklı bir işletmeye aittir.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('booking cannot use an offer belonging to another branch', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Ana Şube',
        'slug' => 'ana-sube-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $otherBranch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Diğer Şube',
        'slug' => 'diger-sube-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Şube',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OFFER-BRANCH-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Şube Teklif Kategorisi',
        'slug' => 'branch-offer-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Şube Teklif Tedavisi',
        'slug' => 'branch-offer-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Şube',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $otherBranch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Başka Şube Teklifi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $otherBranch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Başka Şube Teklifi',
        'description' => 'Başka şubeye ait teklif.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'accepted',
        'responded_at' => now(),
        'treatment_id' => $treatment->id,
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
        offer: $offer,
    ))->toThrow(
        RuntimeException::class,
        'Teklif farklı bir şubeye aittir.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('booking cannot use a different treatment than the offer', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Tedavi Test Şubesi',
        'slug' => 'treatment-offer-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Tedavi',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OFFER-TREATMENT-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Tedavi Teklif Kategorisi',
        'slug' => 'treatment-offer-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $offerTreatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Teklif Tedavisi',
        'slug' => 'offer-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $selectedTreatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Farklı Tedavi',
        'slug' => 'different-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Tedavi',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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
        [
            'branch_id' => $branch->id,
            'treatment_id' => $offerTreatment->id,
            'duration_minutes' => 60,
            'is_online_bookable' => true,
            'is_active' => true,
            'is_offer_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'branch_id' => $branch->id,
            'treatment_id' => $selectedTreatment->id,
            'duration_minutes' => 60,
            'is_online_bookable' => true,
            'is_active' => true,
            'is_offer_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('doctor_treatment')->insert([
        [
            'doctor_id' => $doctor->id,
            'treatment_id' => $offerTreatment->id,
            'duration_minutes' => 60,
            'is_active' => true,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'doctor_id' => $doctor->id,
            'treatment_id' => $selectedTreatment->id,
            'duration_minutes' => 60,
            'is_active' => true,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Tedavi Teklifi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Tedavi Teklifi',
        'description' => 'Belirli bir tedavi için teklif.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'accepted',
        'responded_at' => now(),
        'treatment_id' => $offerTreatment->id,
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $selectedTreatment,
        startsAt: Carbon::parse($startsAt),
        offer: $offer,
    ))->toThrow(
        RuntimeException::class,
        'Teklifteki tedavi ile seçilen tedavi uyuşmuyor.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('pending offer cannot be converted into an appointment', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Pending Teklif Şubesi',
        'slug' => 'pending-offer-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Pending',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OFFER-PENDING-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Pending Teklif Kategorisi',
        'slug' => 'pending-offer-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Pending Teklif Tedavisi',
        'slug' => 'pending-offer-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Pending',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Pending Teklif',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Bekleyen Teklif',
        'description' => 'Henüz kabul edilmemiş teklif.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'pending',
        'responded_at' => null,
        'treatment_id' => $treatment->id,
    ]);

    expect(fn () => app(AppointmentBookingService::class)->book(
        businessId: $business->id,
        branchId: $branch->id,
        patient: $patient,
        doctor: $doctor,
        treatment: $treatment,
        startsAt: Carbon::parse($startsAt),
        offer: $offer,
    ))->toThrow(
        RuntimeException::class,
        'Randevu oluşturmak için teklifin kabul edilmiş olması gerekir.'
    );

    $this->assertDatabaseCount('appointments', 0);
});

test('patient can create appointment through accepted offer endpoint', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'API Teklif Şubesi',
        'slug' => 'api-offer-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'API',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'API-OFFER-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'API Teklif Kategorisi',
        'slug' => 'api-offer-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'API Teklif Tedavisi',
        'slug' => 'api-offer-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'API',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'API Teklif',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'API Teklif',
        'description' => 'API booking testi.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'accepted',
        'responded_at' => now(),
        'treatment_id' => $treatment->id,
    ]);

    $this->actingAs($user);

    $response = $this->postJson(
        "/api/offers/{$offer->id}/appointments",
        [
            'branch_id' => $branch->id,
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->toISOString(),
            'notes' => 'API booking test',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.appointment.offer_id',
            $offer->id
        );

    $this->assertDatabaseHas('appointments', [
        'offer_id' => $offer->id,
        'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id,
        'status' => 'pending',
    ]);
});

test('patient cannot create appointment through another patients offer', function () {
    $offerOwner = User::factory()->create();
    $attacker = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Güvenlik Test Şubesi',
        'slug' => 'security-offer-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Güvenlik',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'SECURITY-OFFER-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Güvenlik Kategorisi',
        'slug' => 'security-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Güvenlik Tedavisi',
        'slug' => 'security-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $offerPatient = PatientProfile::create([
        'user_id' => $offerOwner->id,
        'first_name' => 'Teklif',
        'last_name' => 'Sahibi',
        'phone' => '05550000001',
    ]);

    $attackerPatient = PatientProfile::create([
        'user_id' => $attacker->id,
        'first_name' => 'Yetkisiz',
        'last_name' => 'Hasta',
        'phone' => '05550000002',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $offerPatient->id,
        'subject' => 'Güvenlik Teklifi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $offerPatient->id,
        'created_by' => $offerOwner->id,
        'title' => 'Özel Teklif',
        'description' => 'Sadece teklif sahibine ait.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'accepted',
        'responded_at' => now(),
        'treatment_id' => $treatment->id,
    ]);

    $this->actingAs($attacker);

    $response = $this->postJson(
        "/api/offers/{$offer->id}/appointments",
        [
            'branch_id' => $branch->id,
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->toISOString(),
            'notes' => 'Yetkisiz erişim testi',
        ]
    );

    $response->assertForbidden();

    $this->assertDatabaseCount('appointments', 0);
});

test('patient cannot create appointment through a non accepted offer endpoint', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Bekleyen Teklif API Şubesi',
        'slug' => 'pending-api-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Pending',
        'last_name' => 'API Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'PENDING-API-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Pending API Kategorisi',
        'slug' => 'pending-api-category-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Pending API Tedavisi',
        'slug' => 'pending-api-treatment-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Pending',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
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

    $startsAt = now()->addDays(7)->setTime(10, 0, 0);

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Bekleyen API Teklifi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'created_by' => $user->id,
        'title' => 'Bekleyen Teklif',
        'description' => 'Henüz kabul edilmemiş teklif.',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(7),
        'status' => 'pending',
        'responded_at' => null,
        'treatment_id' => $treatment->id,
    ]);

    $this->actingAs($user);

    $response = $this->postJson(
        "/api/offers/{$offer->id}/appointments",
        [
            'branch_id' => $branch->id,
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->toISOString(),
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonPath('message', 'Randevu oluşturmak için teklifin kabul edilmiş olması gerekir.');

    $this->assertDatabaseCount('appointments', 0);
});