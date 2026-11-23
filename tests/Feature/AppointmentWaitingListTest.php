<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Models\WaitingList;
use App\Notifications\NotificationType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function waitingListBookingFixture(): array
{
    $branch = Branch::factory()->create();
    $person = Person::factory()->for($branch->business)->for($branch)->create();
    $doctor = Doctor::factory()->for($person)->create(['is_public' => true]);
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id, 'name' => 'Bekleme listesi deneme tedavisi',
        'slug' => 'bekleme-listesi-deneme', 'is_active' => true, 'duration_minutes' => 30,
    ]);
    $branch->treatments()->attach($treatment->id, ['is_active' => true, 'is_online_bookable' => true]);
    $doctor->branches()->attach($branch->id, ['status' => 'active']);
    $doctor->treatments()->attach($treatment->id, ['is_active' => true]);
    DoctorWorkingHour::create([
        'doctor_id' => $doctor->id, 'branch_id' => $branch->id,
        'day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '09:30', 'is_active' => true,
    ]);
    $branch->appointmentSetting()->create(['online_booking_enabled' => true, 'is_active' => true, 'maximum_booking_days' => 15]);
    $appointment = Appointment::create([
        'business_id' => $branch->business_id, 'branch_id' => $branch->id, 'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id, 'status' => 'confirmed',
        'starts_at' => '2026-12-04 09:00:00', 'ends_at' => '2026-12-04 09:30:00',
    ]);
    $booking = '/api/patient/appointments/booking-branches/'.$branch->id;
    $selection = ['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'date' => '2026-12-04'];

    return [$branch, $appointment, $booking, $selection];
}

function waitingListPatient(): User
{
    $patient = User::factory()->create();
    $patient->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);

    return $patient;
}

test('the patient can join a full date once and only manage their own request', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [, , $booking, $selection] = waitingListBookingFixture();
    $patient = waitingListPatient();
    $otherPatient = waitingListPatient();
    $slotsUrl = $booking.'/slots?'.http_build_query($selection);

    $this->postJson($booking.'/waiting-list', $selection)->assertUnauthorized();
    $this->actingAs($patient, 'sanctum')->getJson($slotsUrl)->assertOk()
        ->assertJsonPath('data.can_wait', true)->assertJsonPath('data.waiting_list_id', null);
    $id = $this->postJson($booking.'/waiting-list', $selection + ['patient_profile_id' => $otherPatient->patientProfile->id])
        ->assertCreated()->json('data.id');
    $this->postJson($booking.'/waiting-list', $selection)->assertOk()->assertJsonPath('data.id', $id);
    $this->getJson($slotsUrl)->assertOk()->assertJsonPath('data.waiting_list_id', $id);
    $this->assertDatabaseCount('waiting_lists', 1);
    $this->assertDatabaseHas('waiting_lists', [
        'id' => $id, 'patient_profile_id' => $patient->patientProfile->id,
        'patient_name' => null, 'patient_phone' => null, 'patient_email' => null, 'status' => 'waiting',
    ]);

    $requestUrl = '/api/patient/appointments/waiting-list/'.$id;
    $this->actingAs($otherPatient, 'sanctum')->getJson($requestUrl)->assertNotFound();
    $this->deleteJson($requestUrl)->assertNotFound();
    $this->actingAs($patient, 'sanctum')->deleteJson($requestUrl)->assertOk();
    $this->getJson($slotsUrl)->assertOk()->assertJsonPath('data.waiting_list_id', null);
    $this->assertDatabaseHas('waiting_lists', ['id' => $id, 'status' => 'cancelled']);
});

test('a newly available time sends one notification and opens the exact booking selection', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [$branch, $appointment, $booking, $selection] = waitingListBookingFixture();
    $patient = waitingListPatient();
    $cancelledPatient = waitingListPatient();
    $oldPatient = waitingListPatient();
    $id = $this->actingAs($patient, 'sanctum')->postJson($booking.'/waiting-list', $selection)
        ->assertCreated()->json('data.id');
    $cancelledId = $this->actingAs($cancelledPatient, 'sanctum')->postJson($booking.'/waiting-list', $selection)
        ->assertCreated()->json('data.id');
    $this->deleteJson('/api/patient/appointments/waiting-list/'.$cancelledId)->assertOk();
    $oldPatient->patientProfile->waitingLists()->create([
        'business_id' => $branch->business_id, 'branch_id' => $branch->id, 'preferred_date' => $selection['date'],
        'doctor_id' => $selection['doctor_id'], 'treatment_id' => $selection['treatment_id'], 'source' => 'medloby',
    ]);

    $this->artisan('medloby:notify-waiting-list')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 0);
    $appointment->update(['status' => 'cancelled']);
    $branch->business->update(['status' => 'suspended']);
    $this->artisan('medloby:notify-waiting-list')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 0);

    $branch->business->update(['status' => 'active']);
    $this->artisan('medloby:notify-waiting-list')->assertSuccessful();
    $this->artisan('medloby:notify-waiting-list')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('waiting_lists', ['id' => $id, 'status' => 'notified', 'source' => WaitingList::ONLINE_BOOKING]);
    $this->assertDatabaseCount('appointments', 1);

    $notice = $this->actingAs($patient, 'sanctum')->getJson('/api/notifications/unread')->assertOk()
        ->assertJsonPath('data.data.0.data.notification_type', NotificationType::WAITING_LIST_AVAILABILITY)
        ->json('data.data.0.data.waiting_list_id');
    expect($notice)->toBe($id);
    $this->getJson('/api/patient/appointments/waiting-list/'.$notice)->assertOk()
        ->assertJsonPath('data', ['branch_id' => $branch->id] + $selection);
    $this->getJson($booking.'/slots?'.http_build_query($selection))->assertOk()
        ->assertJsonPath('data.slots.0', '2026-12-04 09:00:00')->assertJsonPath('data.can_wait', false);
});

test('the waiting list rejects dates outside booking settings and dates with available times', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [, $appointment, $booking, $selection] = waitingListBookingFixture();
    $this->actingAs(waitingListPatient(), 'sanctum')->postJson($booking.'/waiting-list', [...$selection, 'date' => '2026-11-30'])
        ->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->postJson($booking.'/waiting-list', [...$selection, 'date' => '2026-12-31'])
        ->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->postJson($booking.'/waiting-list', [...$selection, 'doctor_id' => 999999])->assertNotFound();
    $appointment->update(['status' => 'cancelled']);
    $this->postJson($booking.'/waiting-list', $selection)->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->assertDatabaseCount('waiting_lists', 0);
});

test('the waiting list saves its notification until the open time fits the patients schedule', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [, $appointment, $booking, $selection] = waitingListBookingFixture();
    $patient = waitingListPatient();
    $otherBranch = Branch::factory()->create();
    $otherAppointment = $patient->patientProfile->appointments()->create([
        'business_id' => $otherBranch->business_id, 'branch_id' => $otherBranch->id, 'status' => 'pending',
        'starts_at' => '2026-12-04 09:00:00', 'ends_at' => '2026-12-04 09:30:00',
    ]);
    $id = $this->actingAs($patient, 'sanctum')->postJson($booking.'/waiting-list', $selection)->assertCreated()->json('data.id');
    $appointment->update(['status' => 'cancelled']);

    $this->artisan('medloby:notify-waiting-list')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseHas('waiting_lists', ['id' => $id, 'status' => 'waiting']);

    $otherAppointment->update(['status' => 'cancelled']);
    $this->artisan('medloby:notify-waiting-list')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('waiting_lists', ['id' => $id, 'status' => 'notified']);
});
