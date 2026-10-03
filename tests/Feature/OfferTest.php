<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createOfferTestData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $patientUser = User::factory()->create();

    $businessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Teklif testi',
        'status' => 'open',
    ]);

    return [
        'business' => $business,
        'patientUser' => $patientUser,
        'businessUser' => $businessUser,
        'patientProfile' => $patientProfile,
        'conversation' => $conversation,
    ];
}

function createOfferBookingData(): array
{
    $data = createOfferTestData();

    $branch = DB::table('branches')->insertGetId([
        'business_id' => $data['business']->id,
        'name' => 'Teklif Test Şubesi',
        'slug' => 'teklif-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $person = Person::create([
        'business_id' => $data['business']->id,
        'branch_id' => $branch,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'email' => 'doctor' . uniqid() . '@example.com',
        'phone' => '05551111111',
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'TEST-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Teklif Test Kategorisi',
        'slug' => 'teklif-test-kategorisi-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Teklif Test Tedavisi',
        'slug' => 'teklif-test-tedavisi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branch,
        'treatment_id' => $treatment->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
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

    DoctorWorkingHour::create([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $conversation = $data['conversation'];

    $conversation->update([
        'branch_id' => $branch,
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $data['business']->id,
        'branch_id' => $branch,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => $treatment->id,
        'created_by' => $data['businessUser']->id,
        'title' => 'Randevu Teklifi',
        'description' => 'Randevu test teklifi',
        'amount' => 2500,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'accepted',
    ]);

    return [
        'business' => $data['business'],
        'businessUser' => $data['businessUser'],
        'patientUser' => $data['patientUser'],
        'patientProfile' => $data['patientProfile'],
        'conversation' => $conversation->fresh(),
        'branch' => DB::table('branches')->find($branch),
        'doctor' => $doctor,
        'treatment' => $treatment,
        'offer' => $offer,
        'startsAt' => $startsAt,
    ];
}

test('patient can view offers belonging to their conversation', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Test Teklif',
        'description' => 'Test teklif açıklaması',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertOk();

    $response->assertJsonFragment([
        'id' => $offer->id,
    ]);
});

test('patient can accept their own offer', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Kabul Edilecek Teklif',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif kabul edildi.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $offer->id,
        'status' => 'accepted',
    ]);
});

test('patient cannot accept another patients offer', function () {
    $data = createOfferTestData();

    $otherUser = User::factory()->create();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Başkasının Teklifi',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('offers', [
        'id' => $offer->id,
        'status' => 'pending',
    ]);
});

test('patient cannot accept expired offer', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Süresi Dolmuş Teklif',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->subDay(),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklifin geçerlilik süresi dolmuştur.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $offer->id,
        'status' => 'expired',
    ]);
});

test('patient cannot accept already accepted offer', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'created_by' => $data['businessUser']->id,
        'title' => 'Kabul Edilmiş Teklif',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'accepted',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklif artık kabul edilemez.',
    ]);
});

test('patient can create appointment from accepted offer', function () {
    $data = createOfferBookingData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $data['branch']->id,
                'doctor_id' => $data['doctor']->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
                'notes' => 'Teklif üzerinden randevu',
            ]
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Randevu başarıyla oluşturuldu.',
    ]);

    $this->assertDatabaseHas('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'status' => 'pending',
    ]);
});

test('patient cannot create appointment from another patients offer', function () {
    $data = createOfferBookingData();

    $otherUser = User::factory()->create();

    $response = $this
        ->actingAs($otherUser)
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $data['branch']->id,
                'doctor_id' => $data['doctor']->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
            ]
        );

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu teklif üzerinden randevu oluşturma yetkiniz yok.',
    ]);
});

test('patient cannot create appointment from pending offer', function () {
    $data = createOfferBookingData();

    $data['offer']->update([
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $data['branch']->id,
                'doctor_id' => $data['doctor']->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
            ]
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Randevu oluşturmak için teklifin kabul edilmiş olması gerekir.',
    ]);
});

test('patient cannot create appointment from expired offer', function () {
    $data = createOfferBookingData();

    $data['offer']->update([
        'valid_until' => now()->subDay(),
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $data['branch']->id,
                'doctor_id' => $data['doctor']->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
            ]
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklifin geçerlilik süresi dolmuştur.',
    ]);
});

test('patient cannot create appointment in branch different from offer branch', function () {
    $data = createOfferBookingData();

    $otherBranchId = DB::table('branches')->insertGetId([
        'business_id' => $data['business']->id,
        'name' => 'Diğer Teklif Şubesi',
        'slug' => 'diger-teklif-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $otherBranchId,
                'doctor_id' => $data['doctor']->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
            ]
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklif farklı bir şube için oluşturulmuştur.',
    ]);
});

test('patient cannot use doctor from another business', function () {
    $data = createOfferBookingData();

    $otherBusiness = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $otherBranchId = DB::table('branches')->insertGetId([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer İşletme Şubesi',
        'slug' => 'diger-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Sisli',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $person = Person::create([
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranchId,
        'first_name' => 'Başka',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'email' => 'otherdoctor' . uniqid() . '@example.com',
        'phone' => '05552222222',
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
    ]);

    $otherDoctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'OTHER-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $otherDoctor->id,
        'branch_id' => $otherBranchId,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $data['branch']->id,
                'doctor_id' => $otherDoctor->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
            ]
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Seçilen doktor bu işletmeye ait değil.',
    ]);
});

test('patient cannot use doctor from another branch', function () {
    $data = createOfferBookingData();

    $otherBranchId = DB::table('branches')->insertGetId([
        'business_id' => $data['business']->id,
        'name' => 'İkinci Test Şubesi',
        'slug' => 'ikinci-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Sisli',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $person = Person::create([
        'business_id' => $data['business']->id,
        'branch_id' => $otherBranchId,
        'first_name' => 'İkinci',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'email' => 'branchdoctor' . uniqid() . '@example.com',
        'phone' => '05553333333',
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
    ]);

    $otherDoctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'BRANCH-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $otherDoctor->id,
        'branch_id' => $otherBranchId,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $data['branch']->id,
                'doctor_id' => $otherDoctor->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
            ]
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Seçilen doktor bu şubede aktif olarak çalışmıyor.',
    ]);
});

test('patient cannot create appointment for treatment unavailable at offer branch', function () {
    $data = createOfferBookingData();

    $otherTreatmentCategory = TreatmentCategory::create([
        'name' => 'Başka Tedavi Kategorisi',
        'slug' => 'baska-tedavi-kategorisi-' . uniqid(),
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $otherTreatment = Treatment::create([
        'treatment_category_id' => $otherTreatmentCategory->id,
        'name' => 'Başka Tedavi',
        'slug' => 'baska-tedavi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $data['offer']->update([
        'treatment_id' => $otherTreatment->id,
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/appointments",
            [
                'branch_id' => $data['branch']->id,
                'doctor_id' => $data['doctor']->id,
                'starts_at' => $data['startsAt']->toDateTimeString(),
            ]
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Seçilen tedavi bu şubede bulunmuyor.',
    ]);
});