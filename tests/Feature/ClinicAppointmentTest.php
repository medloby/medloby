<?php

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

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
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Paket', 'last_name' => 'Hastası']);
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
        ->assertCreated()->assertJsonCount(2, 'data.appointments');
    $first = $offer->appointments()->orderBy('starts_at')->firstOrFail();
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
