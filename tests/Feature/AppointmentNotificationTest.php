<?php

use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Notifications\NotificationType;
use App\Services\AppointmentBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function createAppointmentNotificationTestData(): array
{
    $business = Business::factory()->create([
        'name' => 'Bildirim Test Kliniği',
        'status' => 'active',
        'is_verified' => true,
        'email' => 'clinic@example.com',
    ]);

    $businessUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $patientUser = User::factory()->create([
        'email' => 'patient@example.com',
        'email_verified_at' => now(),
    ]);

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $person = Person::create([
        'business_id' => $business->id,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'type' => 'doctor',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'TEST-' . uniqid(),
        'specialty' => 'Diş Hekimi',
        'bio' => 'Bildirim test doktoru.',
        'profile_photo' => null,
        'status' => 'active',
        'is_public' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Bildirim Test Şubesi',
        'slug' => 'bildirim-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $doctor->branches()->attach($branch->id, [
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
    ]);

    /*
     * Randevu testlerinde doktorun çalışma saatleri
     * mutlaka tanımlı olmalıdır.
     *
     * now()->addDay() hangi güne denk geliyorsa
     * o gün için 09:00 - 18:00 çalışma saati oluşturuyoruz.
     */
    $workingDay = now()
        ->addDay()
        ->dayOfWeek;

    DB::table('doctor_working_hours')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $workingDay,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $category = TreatmentCategory::create([
        'parent_id' => null,
        'name' => 'Bildirim Test Kategorisi',
        'slug' => 'bildirim-test-kategorisi-' . uniqid(),
        'description' => null,
        'image' => null,
        'icon' => null,
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Bildirim Test Tedavisi',
        'slug' => 'bildirim-test-tedavisi-' . uniqid(),
        'description' => null,
        'duration_minutes' => 60,
        'preparation' => null,
        'aftercare' => null,
        'included_services' => null,
        'excluded_services' => null,
        'image' => null,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    /*
     * Doktorun bu tedaviyi aktif olarak yapabildiğini
     * tanımlıyoruz.
     */
    $doctor->treatments()->attach($treatment->id, [
        'duration_minutes' => 60,
        'is_active' => true,
        'notes' => null,
    ]);

    /*
     * Şubenin bu tedaviyi aktif ve online randevuya
     * açık olarak sunduğunu tanımlıyoruz.
     */
    $branch->treatments()->attach($treatment->id, [
        'is_active' => true,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'duration_minutes' => 60,
    ]);

    return [
        'business' => $business,
        'businessUser' => $businessUser,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'doctor' => $doctor,
        'branch' => $branch,
        'treatment' => $treatment,
    ];
}

test('new appointment sends email to business email address', function () {
    Mail::fake();

    $data = createAppointmentNotificationTestData();

    $appointment = app(AppointmentBookingService::class)->book(
        $data['business']->id,
        $data['branch']->id,
        $data['patientProfile'],
        $data['doctor'],
        $data['treatment'],
        now()->addDay()->setTime(10, 0),
        'medloby',
        true,
        'Randevu bildirim testi.'
    );

    expect($appointment)
        ->toBeInstanceOf(Appointment::class);

    Mail::assertSent(
        NewAppointmentMail::class,
        function (NewAppointmentMail $mail) use ($data, $appointment) {
            return $mail->hasTo($data['business']->email)
                && $mail->appointment->id === $appointment->id;
        }
    );
});

test('new appointment does not send business email when email notification is disabled', function () {
    Mail::fake();

    $data = createAppointmentNotificationTestData();

    $preferenceService = app(
        \App\Services\BusinessNotificationPreferenceService::class
    );

    $preferenceService->update(
        $data['business'],
        NotificationType::NEW_APPOINTMENT,
        true,
        false
    );

    $appointment = app(AppointmentBookingService::class)->book(
        $data['business']->id,
        $data['branch']->id,
        $data['patientProfile'],
        $data['doctor'],
        $data['treatment'],
        now()->addDay()->setTime(11, 0),
        'medloby',
        true,
        null
    );

    expect($appointment)
        ->toBeInstanceOf(Appointment::class);

    Mail::assertNotSent(
        NewAppointmentMail::class
    );

    Mail::assertSent(
        \App\Mail\NewPatientAppointmentMail::class,
        function (\App\Mail\NewPatientAppointmentMail $mail) use ($data, $appointment) {
            return $mail->hasTo($data['patientUser']->email)
                && $mail->appointment->id === $appointment->id;
        }
    );
});

test('new appointment email contains appointment information', function () {
    Mail::fake();

    $data = createAppointmentNotificationTestData();

    $appointment = app(AppointmentBookingService::class)->book(
        $data['business']->id,
        $data['branch']->id,
        $data['patientProfile'],
        $data['doctor'],
        $data['treatment'],
        now()->addDay()->setTime(14, 30),
        'medloby',
        true,
        'Hasta özel notu.'
    );

    Mail::assertSent(
        NewAppointmentMail::class,
        function (NewAppointmentMail $mail) use ($appointment) {
            return $mail->appointment->id === $appointment->id
                && $mail->appointment->patient_name === 'Test Hasta'
                && $mail->appointment->patient_phone === '05550000000'
                && $mail->appointment->treatment->id === $appointment->treatment_id
                && $mail->appointment->branch->id === $appointment->branch_id
                && $mail->appointment->doctor->id === $appointment->doctor_id;
        }
    );
});