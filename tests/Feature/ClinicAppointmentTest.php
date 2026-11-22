<?php

use App\Mail\NewPatientAppointmentMail;
use App\Models\Branch;
use App\Models\BranchAppointmentSetting;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Notifications\NewPatientAppointmentNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function clinicAppointmentFixture(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $branch = Branch::factory()->for($business)->create();
    $other = Branch::factory()->for($business)->create();
    $person = Person::factory()->for($business)->for($branch)->create();
    $doctor = Doctor::factory()->for($person)->create(['is_public' => false]);
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Takvim Muayenesi', 'slug' => 'takvim-muayenesi', 'is_active' => true, 'duration_minutes' => 30,
    ]);
    foreach ([$branch, $other] as $assigned) {
        $assigned->treatments()->attach($treatment->id, ['is_active' => true, 'is_online_bookable' => false]);
        $doctor->branches()->attach($assigned->id, ['status' => 'active']);
        DoctorWorkingHour::create([
            'doctor_id' => $doctor->id, 'branch_id' => $assigned->id,
            'day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '12:00', 'is_active' => true,
        ]);
    }
    $doctor->treatments()->attach($treatment->id, ['is_active' => true]);
    BranchAppointmentSetting::create([
        'branch_id' => $branch->id, 'online_booking_enabled' => false, 'is_active' => true, 'slot_interval_minutes' => 30,
    ]);
    $calendar = "/api/branches/{$branch->id}/clinic-calendar";
    $otherCalendar = "/api/branches/{$other->id}/clinic-calendar";

    return [$business, $owner, $branch, $other, $doctor, $treatment, $calendar, $otherCalendar];
}

test('online package sessions use the branch confirmation setting and clinic bookings still start pending', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    Mail::fake();
    Notification::fake();
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $doctor->update(['is_public' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $this->actingAs($owner, 'sanctum')->putJson($calendar.'/appointment-settings', [
        'online_booking_enabled' => true, 'default_appointment_status' => 'confirmed',
    ])->assertOk()->assertJsonPath('data.default_appointment_status', 'confirmed');
    $patientUser = User::factory()->create();
    $patient = $patientUser->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'open']);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'İki seanslık paket', 'status' => 'accepted', 'amount' => 100, 'currency' => 'TRY',
        'package_snapshot' => ['branch_id' => $branch->id, 'treatments' => [
            ['treatment_id' => $treatment->id, 'name' => $treatment->name, 'quantity' => 2],
        ]],
    ]);
    $session = ['treatment_id' => $treatment->id, 'doctor_id' => $doctor->id, 'starts_at' => '2026-12-04 09:00:00'];

    $booked = $this->actingAs($patientUser, 'sanctum')->postJson('/api/offers/'.$offer->id.'/appointments', [
        'branch_id' => $branch->id,
        'sessions' => [$session, [...$session, 'starts_at' => '2026-12-04 10:00:00']],
    ])->assertCreated()->assertJsonPath('data.appointments.0.status', 'confirmed')
        ->assertJsonPath('data.appointments.1.status', 'confirmed')->json('data.appointments');

    $this->assertDatabaseHas('appointment_status_histories', [
        'appointment_id' => $booked[0]['id'], 'old_status' => null, 'new_status' => 'confirmed',
    ]);
    $this->getJson('/api/offers/'.$offer->id.'/appointments')->assertOk()->assertJsonPath('data.0.status', 'confirmed');
    Notification::assertSentTo($patientUser, NewPatientAppointmentNotification::class,
        fn (NewPatientAppointmentNotification $notification): bool => $notification->toArray($patientUser)['title'] === 'Randevunuz onaylandı');
    Mail::assertSent(NewPatientAppointmentMail::class, 2);

    $clinicAppointment = $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments', [
        ...$session, 'starts_at' => '2026-12-04 11:00:00', 'patient_name' => 'Danışma Hastası',
    ])->assertCreated()->json('data.id');
    $this->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.appointments.0.status', 'confirmed')
        ->assertJsonPath('data.appointments.2.id', $clinicAppointment)
        ->assertJsonPath('data.appointments.2.status', 'pending');
});

test('patients cannot override the active branch setting when booking a single appointment', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [$business, , $branch, $other, $doctor, $treatment] = clinicAppointmentFixture();
    $doctor->update(['is_public' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $other->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $branch->appointmentSetting()->update(['online_booking_enabled' => true, 'default_appointment_status' => 'confirmed']);
    $other->appointmentSetting()->create(['is_active' => false, 'default_appointment_status' => 'confirmed']);
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $booking = [
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'starts_at' => '2026-12-04 09:00:00',
    ];

    $this->actingAs($patient->user, 'sanctum')->postJson('/api/appointments', [
        ...$booking, 'status' => 'pending', 'source' => 'clinic',
    ])->assertCreated()->assertJsonPath('data.status', 'confirmed');
    $this->postJson('/api/appointments', [
        ...$booking, 'branch_id' => $other->id, 'starts_at' => '2026-12-04 10:00:00', 'status' => 'confirmed',
    ])->assertCreated()->assertJsonPath('data.status', 'pending');
});

test('the appointment buffer reserves doctor time without changing the visit duration', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [, $owner, $branch, , $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $this->actingAs($owner, 'sanctum')->putJson($calendar.'/appointment-settings', [
        'slot_interval_minutes' => 10, 'buffer_before_minutes' => 10, 'buffer_after_minutes' => 20,
    ])->assertOk();
    $this->putJson($otherCalendar.'/appointment-settings', [
        'slot_interval_minutes' => 10, 'buffer_before_minutes' => 10,
    ])->assertOk();
    $query = '?date=2026-12-04&treatment_id='.$treatment->id;
    $clinicSlots = $calendar.'/appointment-options'.$query;
    $otherSlots = $otherCalendar.'/appointment-options'.$query;
    $booking = [
        'patient_name' => 'Deneme Hasta', 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id,
    ];

    $slots = $this->getJson($clinicSlots)->assertOk()->json('data.doctors.0.slots');
    expect($slots[0])->toBe('2026-12-04 09:10:00')
        ->and($slots[array_key_last($slots)])->toBe('2026-12-04 11:10:00');
    $this->postJson($calendar.'/appointments', [...$booking, 'starts_at' => '2026-12-04 09:00:00'])
        ->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    $first = $this->postJson($calendar.'/appointments', [...$booking, 'starts_at' => '2026-12-04 09:10:00'])
        ->assertCreated()->json('data.id');
    $this->assertDatabaseHas('appointments', [
        'id' => $first, 'branch_id' => $branch->id, 'starts_at' => '2026-12-04 09:10:00', 'ends_at' => '2026-12-04 09:40:00',
    ]);
    $this->getJson($otherSlots)->assertOk()->assertJsonPath('data.doctors.0.slots.0', '2026-12-04 10:10:00');
    $this->postJson($otherCalendar.'/appointments', [...$booking, 'starts_at' => '2026-12-04 10:00:00'])
        ->assertUnprocessable()->assertJsonValidationErrors('starts_at');

    $this->postJson($calendar.'/blocks', [
        'starts_at' => '2026-12-04 10:10:00', 'ends_at' => '2026-12-04 10:20:00', 'doctor_id' => $doctor->id,
    ])->assertCreated();
    $this->getJson($clinicSlots)->assertOk()->assertJsonPath('data.doctors.0.slots.0', '2026-12-04 10:30:00');
    $this->assertDatabaseCount('appointments', 1);

    $moved = $this->postJson($calendar.'/appointments/'.$first.'/reschedule', ['starts_at' => '2026-12-04 09:20:00'])
        ->assertOk()->json('data.id');
    $this->assertDatabaseHas('appointments', ['id' => $moved, 'ends_at' => '2026-12-04 09:50:00']);
    $this->postJson($calendar.'/appointments/'.$moved.'/cancel', ['cancellation_reason' => 'Prova iptali'])->assertOk();
    $this->getJson($otherSlots)->assertOk()->assertJsonPath('data.doctors.0.slots.0', '2026-12-04 09:10:00');
});

test('the active branch slot interval controls the available start times and preserves treatment duration', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $doctor->update(['is_public' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $staff = User::factory()->create();
    $business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true])
        ->branches()->attach($branch->id, ['is_active' => true]);
    $settings = $calendar.'/appointment-settings';
    $query = '?date=2026-12-04&treatment_id='.$treatment->id;
    $availability = '/api/appointments/availability'.$query.'&branch_id='.$branch->id.'&doctor_id='.$doctor->id;
    $patientUser = User::factory()->create();
    $patient = $patientUser->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);

    $this->actingAs($staff, 'sanctum')->putJson($settings, ['online_booking_enabled' => true, 'slot_interval_minutes' => 20])
        ->assertOk()->assertJsonPath('data.slot_interval_minutes', 20);
    $this->actingAs($owner, 'sanctum')->getJson($calendar.'/appointment-options'.$query)->assertOk()
        ->assertJsonPath('data.doctors.0.slots.1', '2026-12-04 09:20:00');
    $this->getJson($otherCalendar.'/appointment-options'.$query)->assertOk()
        ->assertJsonPath('data.doctors.0.slots.1', '2026-12-04 09:30:00');
    $this->actingAs($patientUser, 'sanctum')->getJson($availability)->assertOk()
        ->assertJsonPath('data.slot_interval', 20)->assertJsonPath('data.slots.1', '2026-12-04 09:20:00');
    $booking = $this->postJson('/api/appointments', [
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'starts_at' => '2026-12-04 09:00:00',
    ])->assertCreated();
    $this->assertDatabaseHas('appointments', [
        'id' => $booking->json('data.id'), 'ends_at' => '2026-12-04 09:30:00',
    ]);
    $this->getJson($availability)->assertOk()->assertJsonPath('data.slots.0', '2026-12-04 09:40:00');

    $this->actingAs($staff, 'sanctum')->putJson($settings, ['is_active' => false])->assertOk();
    $this->actingAs($patientUser, 'sanctum')->getJson($availability)->assertOk()
        ->assertJsonPath('data.slot_interval', 30)->assertJsonPath('data.slots.0', '2026-12-04 09:30:00');
});

test('patient booking uses the clinic clock and respects the online booking window', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-12-04 07:15:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $branch->appointmentSetting()->update(['online_booking_enabled' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $doctor->update(['is_public' => true]);
    foreach ([6, 0] as $day) {
        $doctor->workingHours()->create([
            'branch_id' => $branch->id, 'day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '12:00', 'is_active' => true,
        ]);
    }
    $patientUser = User::factory()->create();
    $patient = $patientUser->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'open']);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'Bir seanslık paket', 'status' => 'accepted', 'amount' => 100, 'currency' => 'TRY',
        'package_snapshot' => ['branch_id' => $branch->id, 'treatments' => [
            ['treatment_id' => $treatment->id, 'name' => $treatment->name, 'quantity' => 1],
        ]],
    ]);
    $packageBooking = '/api/offers/'.$offer->id.'/appointments';
    $query = '?branch_id='.$branch->id.'&treatment_id='.$treatment->id.'&date=';
    $session = ['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id];
    $booking = fn (string $start) => ['branch_id' => $branch->id, 'sessions' => [[...$session, 'starts_at' => $start]]];

    $this->actingAs($owner, 'sanctum')->getJson($calendar.'/appointment-options'.$query.'2026-12-04')->assertOk()
        ->assertJsonPath('data.doctors.0.slots.0', '2026-12-04 10:30:00');
    $this->actingAs($patientUser, 'sanctum')->getJson($packageBooking.'/options'.$query.'2026-12-04')->assertOk()
        ->assertJsonPath('data.doctors.0.slots.0', '2026-12-04 10:30:00');
    $this->postJson($packageBooking, $booking('2026-12-04 09:00:00'))->assertUnprocessable();
    $this->postJson('/api/appointments', [
        ...$session, 'business_id' => $business->id, 'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id, 'starts_at' => '2026-12-04 09:00:00',
    ])->assertUnprocessable();

    $branch->appointmentSetting()->update(['minimum_booking_notice_minutes' => 45]);
    $this->getJson($packageBooking.'/options'.$query.'2026-12-04')->assertOk()
        ->assertJsonPath('data.doctors.0.slots.0', '2026-12-04 11:00:00');
    $this->postJson($packageBooking, $booking('2026-12-04 10:30:00'))->assertUnprocessable();

    $this->travelTo(Carbon::parse('2026-12-03 22:15:00', 'UTC'));
    $branch->appointmentSetting()->update(['same_day_booking_enabled' => false, 'maximum_booking_days' => 1]);
    $this->getJson($packageBooking.'/options'.$query.'2026-12-03')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->getJson($packageBooking.'/options'.$query.'2026-12-04')->assertOk()->assertJsonPath('data.doctors', []);
    $this->postJson($packageBooking, $booking('2026-12-04 10:30:00'))->assertUnprocessable();
    $this->getJson($packageBooking.'/options'.$query.'2026-12-06')->assertOk()->assertJsonPath('data.doctors', []);
    $this->postJson($packageBooking, $booking('2026-12-06 10:30:00'))->assertUnprocessable();
    $this->getJson('/api/appointments/availability'.$query.'2026-12-04&doctor_id='.$doctor->id.'&require_online_bookable=0')
        ->assertOk()->assertJsonPath('data.slots', []);
    $this->actingAs($owner, 'sanctum')->getJson($calendar.'/appointment-options'.$query.'2026-12-04')->assertOk()
        ->assertJsonPath('data.doctors.0.slots.0', '2026-12-04 09:00:00');
    $this->assertDatabaseCount('appointments', 0);

    $branch->appointmentSetting()->update(['is_active' => false]);
    $this->actingAs($patientUser, 'sanctum')->getJson($packageBooking.'/options'.$query.'2026-12-06')->assertOk()
        ->assertJsonPath('data.doctors.0.slots.0', '2026-12-06 09:00:00');
    $branch->appointmentSetting()->update(['is_active' => true]);
    $this->getJson($packageBooking.'/options'.$query.'2026-12-05')->assertOk()
        ->assertJsonPath('data.doctors.0.slots.0', '2026-12-05 09:00:00');
    $appointment = $this->postJson($packageBooking, $booking('2026-12-05 09:00:00'))->assertCreated()->json('data.appointments.0.id');
    $this->actingAs($owner, 'sanctum')->getJson($calendar.'/appointments/'.$appointment)->assertOk()
        ->assertJsonPath('data.starts_at', '2026-12-05 09:00:00');
});

test('clinic calendar books a patient known to the branch and prevents the same doctor being booked twice across branches', function () {
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    [$business, $owner, $branch, $other, $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
    $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'open']);
    $payload = [
        'patient_profile_id' => $patient->id, 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id,
        'starts_at' => '2026-12-04 10:00:00',
    ];
    $query = '?date=2026-12-04&treatment_id='.$treatment->id;

    $this->getJson($calendar.'/appointment-options')->assertUnauthorized();
    $this->actingAs($owner, 'sanctum')->getJson($calendar.'/patients?search=Deneme%20Hasta')
        ->assertOk()->assertExactJson(['success' => true, 'data' => [['id' => $patient->id, 'name' => 'Deneme Hasta']]]);
    $this->getJson($otherCalendar.'/patients?search=Deneme')->assertOk()->assertJsonPath('data', []);
    $this->getJson($calendar.'/appointment-options'.$query)->assertOk()
        ->assertJsonPath('data.doctors.0.id', $doctor->id)
        ->assertJsonPath('data.doctors.0.slots.2', '2026-12-04 10:00:00');
    $this->postJson($otherCalendar.'/appointments', $payload)->assertUnprocessable()->assertJsonValidationErrors('patient_profile_id');
    $appointment = $this->postJson($calendar.'/appointments', $payload)->assertCreated()->json('data.id');
    $this->assertDatabaseHas('appointments', [
        'id' => $appointment, 'patient_profile_id' => $patient->id, 'branch_id' => $branch->id, 'source' => 'clinic',
    ]);
    $this->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.appointments.0.patient', 'Deneme Hasta')
        ->assertJsonPath('data.appointments.0.actions', ['confirm', 'cancel', 'reschedule']);

    $manual = [...$payload, 'patient_profile_id' => null, 'patient_name' => 'Şubeye gelen hasta'];
    $this->getJson($otherCalendar.'/appointment-options'.$query)->assertOk()->assertJsonMissing(['2026-12-04 10:00:00']);
    $this->postJson($otherCalendar.'/appointments', $manual)->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    $this->postJson($otherCalendar.'/appointments', [...$manual, 'starts_at' => '2026-12-04 10:30:00'])->assertCreated();
    $this->assertDatabaseHas('appointments', [
        'patient_profile_id' => null, 'patient_name' => 'Şubeye gelen hasta', 'branch_id' => $other->id,
    ]);
    $this->travelTo(now()->setDate(2026, 12, 4)->setTime(7, 15));
    $this->postJson($otherCalendar.'/appointments', [...$manual, 'starts_at' => '2026-12-04 09:00:00'])
        ->assertUnprocessable()->assertJsonValidationErrors('starts_at');

    $doctor->update(['is_public' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $this->actingAs($patient->user, 'sanctum')->postJson('/api/appointments', [
        ...$payload, 'business_id' => $business->id, 'branch_id' => $branch->id,
        'starts_at' => '2026-12-04 11:00:00', 'require_online_bookable' => false,
    ])->assertUnprocessable();
    $this->assertDatabaseCount('appointments', 2);
});

test('clinic appointment card includes the saved notes and is available only in an authorized branch', function () {
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    [$business, $owner, $branch, $other, $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $patientUser = User::factory()->create(['email' => 'hasta@example.test']);
    $patient = $patientUser->patientProfile()->create([
        'first_name' => 'Deneme', 'last_name' => 'Hasta', 'phone' => '+90 555 123 45 67',
    ]);
    $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'open']);
    $payload = ['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'starts_at' => '2026-12-04 10:00:00'];
    $notes = "Önceki film kaydı getirilecek.\nGirişte danışmaya uğrayacak.";
    $this->getJson($calendar.'/appointments/1')->assertUnauthorized();
    $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments', [
        ...$payload, 'patient_profile_id' => $patient->id, 'notes' => str_repeat('x', 2001),
    ])->assertUnprocessable()->assertJsonValidationErrors('notes');
    $linked = $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments', [
        ...$payload, 'patient_profile_id' => $patient->id, 'notes' => $notes,
    ])->assertCreated()->json('data.id');
    $manual = $this->postJson($calendar.'/appointments', [
        ...$payload, 'patient_name' => 'Şubeye gelen hasta', 'starts_at' => '2026-12-04 10:30:00',
    ])->assertCreated()->json('data.id');
    $card = $calendar.'/appointments/'.$linked;
    $this->getJson($calendar.'?date=2026-12-04')->assertOk()->assertJsonPath('data.appointments.0.patient', 'Deneme Hasta')
        ->assertDontSee('+90 555 123 45 67')->assertDontSee('hasta@example.test')->assertJsonMissingPath('data.appointments.0.notes');
    $this->getJson($card)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson([
        'success' => true,
        'data' => [
            'id' => $linked, 'clinic' => $business->name, 'branch' => $branch->name,
            'name' => 'Deneme Hasta', 'phone' => '+90 555 123 45 67', 'email' => 'hasta@example.test',
            'doctor' => trim($doctor->person->first_name.' '.$doctor->person->last_name), 'treatment' => $treatment->name,
            'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00', 'status' => 'pending', 'notes' => $notes,
        ],
    ]);
    $this->getJson($card.'/contact')->assertOk()->assertExactJson([
        'success' => true, 'data' => ['name' => 'Deneme Hasta', 'phone' => '+90 555 123 45 67', 'email' => 'hasta@example.test'],
    ]);
    $this->getJson($calendar.'/appointments/'.$manual)->assertOk()
        ->assertJsonPath('data.name', 'Şubeye gelen hasta')->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.email', null)->assertJsonPath('data.notes', null);
    $this->getJson($otherCalendar.'/appointments/'.$linked)->assertNotFound();

    $staff = User::factory()->create();
    $membership = $business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $membership->branches()->attach($other->id, ['is_active' => true]);
    $this->actingAs($staff, 'sanctum')->getJson($card)->assertForbidden();
    $membership->branches()->attach($branch->id, ['is_active' => true]);
    $this->getJson($card)->assertOk()->assertJsonPath('data.email', 'hasta@example.test');
    $this->actingAs($patientUser, 'sanctum')->getJson($card)->assertForbidden();
    $business->update(['status' => 'suspended']);
    $this->actingAs($owner, 'sanctum')->getJson($card)->assertForbidden();
});

test('crm lead appointments show only the linked patient in the authorized branch and booking requires permission', function () {
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    [$business, $owner, $branch, $other, $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
    foreach ([$branch, $other] as $assigned) {
        $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $assigned->id, 'status' => 'open']);
    }
    $staff = User::factory()->create();
    $membership = $business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $membership->branches()->attach($branch->id, ['is_active' => true]);
    $leadPath = "/api/branches/{$branch->id}/crm/leads";
    $payload = [
        'patient_profile_id' => $patient->id, 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id,
        'starts_at' => '2026-12-04 10:00:00',
    ];
    $this->actingAs($owner, 'sanctum');
    $appointment = $this->postJson($calendar.'/appointments', $payload)->assertCreated()->json('data.id');
    $this->postJson($otherCalendar.'/appointments', [...$payload, 'starts_at' => '2026-12-04 10:30:00'])->assertCreated();
    $this->postJson($calendar.'/appointments', [
        ...$payload, 'patient_profile_id' => null, 'patient_name' => 'Deneme Hasta', 'starts_at' => '2026-12-04 11:00:00',
    ])->assertCreated();
    $lead = $this->postJson($leadPath, ['patient_profile_id' => $patient->id])->assertCreated()->json('data.id');
    $unlinked = $this->postJson($leadPath, ['first_name' => 'Deneme', 'last_name' => 'Hasta'])->assertCreated()->json('data.id');
    $history = $leadPath.'/'.$lead.'/appointments';
    $otherHistory = "/api/branches/{$other->id}/crm/leads/{$lead}/appointments";

    $this->getJson($history)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.patient', ['id' => $patient->id, 'name' => 'Deneme Hasta'])
        ->assertJsonPath('data.can_book', true)->assertJsonCount(1, 'data.appointments.data')
        ->assertJsonPath('data.appointments.data.0', [
            'id' => $appointment, 'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00',
            'status' => 'pending', 'doctor' => $doctor->person->first_name.' '.$doctor->person->last_name,
            'treatment' => $treatment->name,
        ]);
    $this->getJson($leadPath.'/'.$unlinked.'/appointments')->assertOk()->assertJsonPath('data', [
        'patient' => null, 'can_book' => false, 'appointments' => null,
    ]);
    $this->getJson($otherHistory)->assertNotFound();

    $this->actingAs($staff, 'sanctum')->getJson($otherHistory)->assertForbidden();
    $this->getJson($history)->assertOk()->assertJsonPath('data.can_book', false);
    $newBooking = [...$payload, 'starts_at' => '2026-12-04 11:30:00'];
    $this->postJson($calendar.'/appointments', $newBooking)->assertForbidden();
    $permission = Permission::create(['name' => 'appointments.create', 'display_name' => 'Randevu oluştur', 'module' => 'appointments', 'is_active' => true]);
    $staff->permissions()->attach($permission->id, ['business_id' => $business->id, 'is_allowed' => true]);
    $this->getJson($history)->assertOk()->assertJsonPath('data.can_book', true);
    $newAppointment = $this->postJson($calendar.'/appointments', $newBooking)->assertCreated()->json('data.id');
    $this->getJson($history)->assertOk()->assertJsonCount(2, 'data.appointments.data')
        ->assertJsonPath('data.appointments.data.0.id', $newAppointment);
    $this->getJson($leadPath.'/'.$lead)->assertOk()->assertJsonPath('data.status', 'new');
});

test('calendar actions enforce branch access, individual permission and clinic approval', function () {
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    [$business, $owner, $branch, , $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $staff = User::factory()->create();
    $membership = $business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $membership->branches()->attach($branch->id, ['is_active' => true]);
    $payload = [
        'patient_name' => 'Deneme Hasta', 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id,
        'starts_at' => '2026-12-04 10:00:00',
    ];
    $appointment = $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments', $payload)->assertCreated()->json('data.id');
    $entry = $calendar.'/appointments/'.$appointment;
    $this->postJson($otherCalendar.'/appointments/'.$appointment.'/cancel', ['cancellation_reason' => 'Yanlış şube'])->assertNotFound();
    $this->actingAs($staff, 'sanctum')->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.can_create_appointments', false)->assertJsonPath('data.appointments.0.actions', []);
    $this->postJson($calendar.'/appointments', $payload)->assertForbidden();
    $this->postJson($entry.'/confirm')->assertForbidden();

    $confirm = Permission::create(['name' => 'appointments.confirm', 'display_name' => 'Randevuyu onayla', 'module' => 'appointments', 'is_active' => true]);
    $staff->permissions()->attach($confirm->id, ['business_id' => $business->id, 'is_allowed' => true]);
    $this->postJson($entry.'/confirm')->assertOk()->assertJsonPath('data.status', 'confirmed');

    $this->actingAs($owner, 'sanctum');
    $cancel = Permission::create(['name' => 'appointments.cancel', 'display_name' => 'Randevuyu iptal et', 'module' => 'appointments', 'is_active' => true]);
    $owner->permissions()->attach($cancel->id, ['business_id' => $business->id, 'is_allowed' => false]);
    $this->postJson($entry.'/cancel', ['cancellation_reason' => 'Açık izin reddi'])->assertForbidden();
    $this->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.appointments.0.actions', ['complete', 'no-show', 'reschedule']);
    $business->update(['status' => 'suspended']);
    $this->postJson($entry.'/complete')->assertForbidden();
    $this->postJson('/api/appointments/'.$appointment.'/complete')->assertForbidden();
    $this->assertDatabaseHas('appointments', ['id' => $appointment, 'status' => 'confirmed']);
});

test('an accepted package becomes a complete set of calendar sessions and stays linked after rescheduling', function () {
    $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());
    Mail::fake();
    [$business, $owner, $branch, , $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $branch->appointmentSetting()->update(['default_appointment_status' => 'confirmed']);
    $patient = User::factory()->create(['email' => 'paket@example.test'])->patientProfile()->create([
        'first_name' => 'Paket', 'last_name' => 'Hastası', 'phone' => '+90 555 222 33 44',
    ]);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'open']);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'İki seanslık paket', 'status' => 'pending', 'amount' => 100, 'currency' => 'TRY',
        'package_snapshot' => ['branch_id' => $branch->id, 'treatments' => [
            ['treatment_id' => $treatment->id, 'name' => $treatment->name, 'quantity' => 2],
        ]],
    ]);
    $path = '/package-offers/'.$offer->id.'/appointments';
    $session = [
        'treatment_id' => $treatment->id, 'doctor_id' => $doctor->id, 'starts_at' => '2026-12-04 10:00:00',
    ];
    $this->actingAs($owner, 'sanctum')->getJson($calendar.'/package-offers')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson($calendar.$path, ['sessions' => [$session]])->assertNotFound();
    $offer->update(['status' => 'accepted']);
    $this->getJson($otherCalendar.$path.'/options')->assertNotFound();
    $this->getJson($calendar.'/package-offers')->assertOk()->assertJsonPath('data.0.patient', 'Paket Hastası');
    $this->getJson($calendar.$path.'/options?date=2026-12-04&branch_id='.$branch->id.'&treatment_id='.$treatment->id)
        ->assertOk()->assertJsonPath('data.doctors.0.id', $doctor->id);
    $this->postJson($calendar.$path, ['sessions' => [$session]])->assertUnprocessable();
    $this->postJson($calendar.$path, ['sessions' => [$session, $session]])->assertUnprocessable();
    expect($offer->appointments()->count())->toBe(0);
    $this->postJson($calendar.$path, ['sessions' => [$session, [...$session, 'starts_at' => '2026-12-04 11:00:00']]])
        ->assertCreated()->assertJsonCount(2, 'data.appointments')
        ->assertJsonMissingPath('data.appointments.0.patient_profile.phone')
        ->assertJsonMissingPath('data.appointments.0.patient_email');
    $first = $offer->appointments()->orderBy('starts_at')->firstOrFail();
    $patient->update(['phone' => '+90 555 000 00 00']);
    $this->getJson($calendar.'/appointments/'.$first->id.'/contact')->assertOk()->assertJsonPath('data', [
        'name' => 'Paket Hastası', 'phone' => '+90 555 222 33 44', 'email' => 'paket@example.test',
    ]);
    $this->assertDatabaseHas('appointment_status_histories', [
        'appointment_id' => $first->id, 'changed_by_user_id' => $owner->id, 'new_status' => 'pending',
    ]);
    $this->postJson($calendar.$path, ['sessions' => [$session, $session]])->assertNotFound();
    $this->getJson($calendar.'?date=2026-12-04')->assertOk()->assertJsonPath('data.appointments.0.package', $offer->title);

    $this->postJson($calendar.'/appointments/'.$first->id.'/reschedule', ['starts_at' => '2026-12-04 11:00:00'])->assertUnprocessable();
    $rescheduled = $this->postJson($calendar.'/appointments/'.$first->id.'/reschedule', ['starts_at' => '2026-12-11 09:30:00'])
        ->assertOk()->assertJsonPath('data.offer_id', $offer->id)->json('data.id');
    $this->postJson($calendar.'/appointments/'.$rescheduled.'/cancel', ['cancellation_reason' => 'Hasta isteği'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->assertDatabaseHas('appointments', ['id' => $first->id, 'status' => 'rescheduled']);
    expect($offer->appointments()->where('status', 'pending')->count())->toBe(1);
    $this->actingAs($patient->user, 'sanctum')->getJson('/api/offers/'.$offer->id.'/appointments')
        ->assertOk()->assertJsonCount(2, 'data');
});
