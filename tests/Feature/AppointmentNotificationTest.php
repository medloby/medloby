<?php

use App\Mail\NewAppointmentMail;
use App\Mail\NewPatientAppointmentMail;
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
use App\Notifications\NewAppointmentNotification;
use App\Notifications\NewPatientAppointmentNotification;
use App\Notifications\NotificationType;
use App\Services\AppointmentBookingService;
use App\Services\BusinessNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

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
        'role' => 'business_owner',
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
        'license_number' => 'TEST-'.uniqid(),
        'specialty' => 'Diş Hekimi',
        'bio' => 'Bildirim test doktoru.',
        'profile_photo' => null,
        'status' => 'active',
        'is_public' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Bildirim Test Şubesi',
        'slug' => 'bildirim-test-subesi-'.uniqid(),
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
        'slug' => 'bildirim-test-kategorisi-'.uniqid(),
        'description' => null,
        'image' => null,
        'icon' => null,
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Bildirim Test Tedavisi',
        'slug' => 'bildirim-test-tedavisi-'.uniqid(),
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

test('online booking can be retried when an in-app notification fails', function () {
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    $data = createAppointmentNotificationTestData();
    $url = "/api/patient/appointments/booking-branches/{$data['branch']->id}";
    $payload = [
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
    ];
    $failNotification = true;
    Exceptions::fake();
    Mail::fake();
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$failNotification): void {
        if ($failNotification && $event->notification instanceof NewPatientAppointmentNotification) {
            throw new RuntimeException('Patient booking notification unavailable.');
        }
    });

    $this->actingAs($data['patientUser'], 'sanctum')->postJson($url, $payload)->assertInternalServerError();

    $this->assertDatabaseCount('appointments', 0);
    $this->assertDatabaseCount('appointment_status_histories', 0);
    $this->assertDatabaseCount('notifications', 0);
    Mail::assertNothingQueued();
    Exceptions::assertReported(RuntimeException::class);

    $failNotification = false;
    $appointmentId = $this->postJson($url, $payload)->assertCreated()->json('data.id');

    $this->assertDatabaseCount('appointments', 1);
    $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $appointmentId]);
    expect($data['patientUser']->notifications()->sole()->data['appointment_id'])->toBe($appointmentId);
    expect($data['businessUser']->notifications()->sole()->data['appointment_id'])->toBe($appointmentId);
    Mail::assertQueued(NewAppointmentMail::class, 1);
    Mail::assertQueued(NewPatientAppointmentMail::class, 1);
});

test('online booking and both in-app notifications survive an email outage', function (string $queue) {
    config(['queue.default' => $queue, 'mail.default' => 'array']);
    Exceptions::fake();
    $offline = true;
    Event::listen(MessageSending::class, function () use (&$offline): void {
        if ($offline) {
            throw new TransportException('Mail transport unavailable');
        }
    });
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    $data = createAppointmentNotificationTestData();
    $data['doctor']->update(['is_public' => true]);

    $appointment = $this->actingAs($data['patientUser'], 'sanctum')
        ->postJson("/api/patient/appointments/booking-branches/{$data['branch']->id}", [
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        ])->assertCreated()->json('data.id');

    $this->assertDatabaseHas('appointments', ['id' => $appointment, 'patient_profile_id' => $data['patientProfile']->id]);
    $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $appointment]);
    expect($data['businessUser']->notifications()->where('type', NewAppointmentNotification::class)->count())->toBe(1);
    expect($data['patientUser']->notifications()->where('type', NewPatientAppointmentNotification::class)->count())->toBe(1);

    if ($queue === 'sync') {
        Exceptions::assertReportedCount(2);

        return;
    }

    Exceptions::assertNothingReported();
    $this->assertDatabaseCount('jobs', 2);
    $workerOptions = ['--once' => true, '--tries' => 2, '--backoff' => 0];
    $this->artisan('queue:work', $workerOptions)->assertSuccessful();
    Exceptions::assertReported(TransportException::class);
    $this->assertDatabaseHas('jobs', ['attempts' => 1]);

    $offline = false;
    $this->artisan('queue:work', $workerOptions)->assertSuccessful();
    $this->artisan('queue:work', $workerOptions)->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
    $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
    $recipients = $messages->map(fn ($mail) => $mail->getOriginalMessage()->getTo()[0]->getAddress());
    expect($recipients->all())->toEqualCanonicalizing([$data['business']->email, $data['patientUser']->email]);
    $this->assertDatabaseCount('notifications', 2);
})->with(['database', 'sync']);

test('new appointment queues email to business email address', function () {
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

    Mail::assertQueued(
        NewAppointmentMail::class,
        function (NewAppointmentMail $mail) use ($data, $appointment) {
            return $mail->hasTo($data['business']->email)
                && $mail->appointment->id === $appointment->id;
        }
    );
});

test('new appointment skips the business email when the email preference is off', function () {
    Mail::fake();

    $data = createAppointmentNotificationTestData();

    $preferenceService = app(
        BusinessNotificationPreferenceService::class
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

    Mail::assertNotQueued(
        NewAppointmentMail::class
    );

    Mail::assertQueued(
        NewPatientAppointmentMail::class,
        function (NewPatientAppointmentMail $mail) use ($data, $appointment) {
            return $mail->hasTo($data['patientUser']->email)
                && $mail->appointment->id === $appointment->id;
        }
    );
});

test('queued appointment email references the correct appointment', function () {
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

    Mail::assertQueued(
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

test('appointment email bodies use an account link and omit identifying and medical information', function () {
    $appointment = new Appointment([
        'patient_name' => 'Deneme Hasta',
        'patient_email' => 'hasta@example.test',
        'patient_phone' => '05551112233',
        'notes' => 'Kişiye özel görüşme notu',
    ]);
    $appointment->setRelation('patientProfile', new PatientProfile(['first_name' => 'Deneme Hasta']));
    $appointment->setRelation('treatment', new Treatment(['name' => 'Özel tedavi bilgisi']));
    $account = route('package-offers').'#gorusmeler';

    foreach ([new NewAppointmentMail($appointment), new NewPatientAppointmentMail($appointment)] as $mail) {
        $mail->assertSeeInHtml($account);
        $mail->assertDontSeeInHtml('Deneme Hasta');
        $mail->assertDontSeeInHtml('hasta@example.test');
        $mail->assertDontSeeInHtml('05551112233');
        $mail->assertDontSeeInHtml('Kişiye özel görüşme notu');
        $mail->assertDontSeeInHtml('Özel tedavi bilgisi');
    }
});
