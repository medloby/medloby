<?php

use App\Mail\NewPatientAppointmentMail;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
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
use App\Notifications\NewAppointmentNotification;
use App\Notifications\NewPatientAppointmentNotification;
use App\Notifications\NotificationType;
use App\Notifications\PatientAppointmentChangedNotification;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
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

test('owner delegated appointment and price permissions apply only to the selected branch', function () {
    Notification::fake();
    [$business, $owner, $branch, $other, $doctor, $treatment, $calendar, $otherCalendar] = clinicAppointmentFixture();
    $staff = User::factory()->create();
    $member = $business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $member->branches()->attach([$branch->id, $other->id]);
    $staffPath = "/api/branches/{$branch->id}/operations/staff/{$member->id}";
    $permissions = ['appointments.create', 'appointments.confirm', 'branches.manage'];
    $date = '?date=2026-12-04';

    $this->actingAs($staff, 'sanctum')->getJson($calendar.$date)->assertOk()->assertJsonPath('data.can_create_appointments', false);
    $this->putJson($staffPath.'/permissions', ['permissions' => $permissions])->assertForbidden();

    $this->actingAs($owner, 'sanctum')->putJson($staffPath.'/permissions', ['permissions' => ['appointments.unknown']])
        ->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    $this->putJson($staffPath.'/permissions', ['permissions' => $permissions])->assertOk();
    $this->getJson("/api/branches/{$branch->id}/operations")->assertOk()->assertJsonPath('data.staff.0.branch_permissions', $permissions);
    $this->getJson("/api/branches/{$other->id}/operations")->assertOk()->assertJsonPath('data.staff.0.branch_permissions', []);

    $visit = $branch->appointments()->create(['business_id' => $business->id, 'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id, 'status' => 'pending', 'starts_at' => '2026-12-04 09:00:00', 'ends_at' => '2026-12-04 09:30:00']);
    $this->actingAs($staff, 'sanctum')->getJson($calendar.$date)->assertOk()->assertJsonPath('data.can_create_appointments', true)
        ->assertJsonPath('data.appointments.0.actions', ['confirm']);
    $this->getJson($otherCalendar.$date)->assertOk()->assertJsonPath('data.can_create_appointments', false);
    $this->postJson($calendar.'/appointments/'.$visit->id.'/confirm')->assertOk()->assertJsonPath('data.status', 'confirmed');

    $price = ['business_id' => $business->id, 'treatment_id' => $treatment->id, 'price_type' => 'fixed', 'price' => 800, 'currency' => 'TRY'];
    $this->postJson('/api/treatment-prices', $price + ['branch_id' => $branch->id])->assertCreated();
    $this->postJson('/api/treatment-prices', $price + ['branch_id' => $other->id])->assertForbidden();
    $this->postJson('/api/treatment-prices', $price)->assertForbidden();
    $branch->update(['status' => 'inactive']);
    $this->postJson('/api/treatment-prices', $price + ['branch_id' => $branch->id])->assertForbidden();
    $branch->update(['status' => 'active']);

    $this->actingAs($owner, 'sanctum')->putJson($staffPath.'/permissions', ['permissions' => []])->assertOk();
    $this->actingAs($staff, 'sanctum')->getJson($calendar.$date)->assertOk()->assertJsonPath('data.can_create_appointments', false);

    $this->actingAs($owner, 'sanctum')->putJson($staffPath.'/permissions', ['permissions' => $permissions])->assertOk();
    $this->putJson($staffPath, ['is_active' => false])->assertOk();
    $this->putJson($staffPath.'/permissions', ['permissions' => $permissions])->assertForbidden();
    $this->putJson($staffPath, ['is_active' => true])->assertOk();
    $this->actingAs($staff, 'sanctum')->getJson($calendar.$date)->assertOk()->assertJsonPath('data.can_create_appointments', false);
});

test('platform permissions remain authoritative over branch delegation', function () {
    [$business, $owner, $branch, , , , $calendar] = clinicAppointmentFixture();
    $staff = User::factory()->create();
    $member = $business->memberships()->create(['user_id' => $staff->id, 'role' => 'manager', 'is_active' => true]);
    $member->branches()->attach($branch);
    $permission = Permission::create(['name' => 'appointments.create', 'display_name' => 'Randevu oluşturma', 'module' => 'appointments']);
    $otherPermission = Permission::create(['name' => 'appointments.confirm', 'display_name' => 'Randevu onaylama', 'module' => 'appointments']);
    $owner->permissions()->attach($otherPermission, ['business_id' => $business->id, 'is_allowed' => false]);
    $operations = "/api/branches/{$branch->id}/operations";
    $path = $operations.'/staff/'.$member->id.'/permissions';

    $this->actingAs($owner, 'sanctum')->putJson($path, ['permissions' => ['appointments.confirm']])->assertForbidden();
    $this->putJson($path, ['permissions' => ['appointments.create']])->assertOk();
    $permission->update(['is_active' => false]);
    $this->actingAs($staff, 'sanctum')->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.can_create_appointments', false);
    $permission->update(['is_active' => true]);
    $staff->permissions()->attach($permission, ['business_id' => $business->id, 'is_allowed' => false]);
    $this->actingAs($owner, 'sanctum');
    $this->getJson($operations)->assertOk()->assertJsonPath('data.staff.0.platform_permissions', ['appointments.create' => false]);
    $this->actingAs($staff, 'sanctum')->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.can_create_appointments', false);
    $this->actingAs($owner, 'sanctum')->putJson($path, ['permissions' => []])->assertOk();
    $this->putJson($path, ['permissions' => ['appointments.create']])->assertForbidden();
});

test('a verified patient can book a standalone visit through the online branch and doctor selection', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    Mail::fake();
    [$business, $owner, $branch, $closed, $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $doctor->update(['is_public' => true]);
    $otherDoctor = Doctor::factory()->for(Person::factory()->for($business)->for($branch))->create(['is_public' => false]);
    $otherDoctor->branches()->attach($branch->id, ['status' => 'active']);
    $otherDoctor->treatments()->attach($treatment->id, ['is_active' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $closed->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $closed->appointmentSetting()->create(['is_active' => true, 'online_booking_enabled' => false]);
    $branch->appointmentSetting()->update(['online_booking_enabled' => true, 'same_day_booking_enabled' => false]);
    $patient = User::factory()->create(['email' => 'patient-booking@example.test'])->patientProfile()->create([
        'first_name' => 'Deneme', 'last_name' => 'Hastası', 'phone' => '+905550001234',
    ]);
    $otherPatient = User::factory()->create()->patientProfile()->create(['first_name' => 'Başka', 'last_name' => 'Hasta']);
    $endpoint = '/api/patient/appointments/booking-branches';
    $selectedBranch = $endpoint.'/'.$branch->id;
    $payload = ['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'starts_at' => '2026-12-04 09:00:00'];
    $slots = $selectedBranch.'/slots?date=2026-12-04&doctor_id='.$doctor->id.'&treatment_id='.$treatment->id;

    $this->getJson($endpoint)->assertUnauthorized();
    $this->actingAs($owner, 'sanctum')->getJson($endpoint)->assertForbidden();
    $this->actingAs($patient->user, 'sanctum')->getJson($endpoint)->assertOk()->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.branches.0.id', $branch->id)->assertJsonMissingPath('data.branches.0.email');
    $this->getJson($selectedBranch.'/treatments')->assertOk()
        ->assertJsonPath('data.branch.clinic', $business->name)->assertJsonPath('data.branch.name', $branch->name)
        ->assertJsonPath('data.treatments.0.id', $treatment->id)
        ->assertJsonCount(1, 'data.treatments.0.doctors')->assertJsonPath('data.treatments.0.doctors.0.id', $doctor->id);
    $this->getJson($endpoint.'/'.$closed->id.'/treatments')->assertNotFound();
    $this->getJson($slots)->assertOk()->assertJsonPath('data.slots.0', '2026-12-04 09:00:00');
    $this->postJson($selectedBranch, [...$payload, 'doctor_id' => $otherDoctor->id])->assertNotFound();
    $this->postJson($selectedBranch, [...$payload, 'starts_at' => '2026-12-01 09:00:00'])->assertUnprocessable();

    $appointment = $this->postJson($selectedBranch, [
        ...$payload, 'notes' => 'Randevu denemesi', 'patient_profile_id' => $otherPatient->id, 'status' => 'confirmed',
    ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonMissingPath('data.patient_email')->json('data.id');

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment, 'patient_profile_id' => $patient->id, 'offer_id' => null, 'notes' => 'Randevu denemesi',
        'patient_email' => 'patient-booking@example.test', 'starts_at' => '2026-12-04 09:00:00',
    ]);
    $this->assertDatabaseHas('appointment_status_histories', [
        'appointment_id' => $appointment, 'changed_by_user_id' => $patient->user_id, 'new_status' => 'pending',
    ]);
    $this->postJson($selectedBranch, $payload)->assertUnprocessable();
    $this->getJson($slots)->assertOk()->assertJsonPath('data.slots.0', '2026-12-04 09:30:00');
    $this->getJson('/api/patient/appointments/'.$appointment)->assertOk()->assertJsonPath('data.id', $appointment);

    $branch->appointmentSetting()->update(['default_appointment_status' => 'confirmed']);
    $second = $this->postJson($selectedBranch, [...$payload, 'starts_at' => '2026-12-04 09:30:00'])
        ->assertCreated()->assertJsonPath('data.status', 'confirmed')->json('data.id');
    $this->assertDatabaseHas('appointments', ['id' => $second, 'status' => 'confirmed']);
    $this->actingAs($owner, 'sanctum')->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.appointments.0.id', $appointment)->assertJsonPath('data.appointments.1.id', $second);
    expect($owner->notifications()->where('type', NewAppointmentNotification::class)->count())->toBe(2);
});

test('the patient appointment list combines standalone and package visits and protects patient details', function () {
    $this->travelTo(Carbon::parse('2026-12-04 05:00:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment] = clinicAppointmentFixture();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $otherPatient = User::factory()->create()->patientProfile()->create(['first_name' => 'Başka', 'last_name' => 'Hasta']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'Deneme tedavi paketi', 'status' => 'accepted', 'amount' => 100,
        'package_snapshot' => ['branch_id' => $branch->id, 'treatments' => [['treatment_id' => $treatment->id, 'quantity' => 1]]],
    ]);
    $visit = [
        'business_id' => $business->id, 'branch_id' => $branch->id, 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id,
        'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00', 'status' => 'confirmed',
        'notes' => 'Özel randevu notu', 'patient_phone' => '+905550000000', 'patient_email' => 'hasta@example.test',
    ];
    $standalone = $patient->appointments()->create($visit);
    $previous = $patient->appointments()->create([...$visit, 'offer_id' => $offer->id, 'status' => 'rescheduled']);
    $package = $patient->appointments()->create([
        ...$visit, 'offer_id' => $offer->id, 'rescheduled_from_appointment_id' => $previous->id,
        'starts_at' => '2026-12-04 11:00:00', 'ends_at' => '2026-12-04 11:30:00',
    ]);
    $past = $patient->appointments()->create([...$visit, 'starts_at' => '2026-12-04 08:00:00', 'ends_at' => '2026-12-04 08:30:00']);
    $cancelled = $patient->appointments()->create([...$visit, 'status' => 'cancelled']);
    $otherVisit = $otherPatient->appointments()->create($visit);
    $path = '/api/patient/appointments';

    $this->getJson($path)->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create(), 'sanctum')->getJson($path)->assertForbidden();
    $this->actingAs($owner, 'sanctum')->getJson($path)->assertForbidden();
    $this->actingAs($patient->user, 'sanctum')->getJson($path.'/'.$otherVisit->id)->assertNotFound();
    $this->getJson($path.'?view=unknown')->assertUnprocessable()->assertJsonValidationErrors('view');

    $this->getJson($path.'?patient_profile_id='.$otherPatient->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(2, 'data.appointments')->assertJsonPath('data.appointments.0.id', $standalone->id)
        ->assertJsonPath('data.appointments.0.business_name', $business->name)->assertJsonPath('data.appointments.0.offer_title', null)
        ->assertJsonPath('data.appointments.0.patient_actions', ['cancel'])
        ->assertJsonPath('data.appointments.1.id', $package->id)->assertJsonPath('data.appointments.1.offer_title', $offer->title)
        ->assertJsonMissingPath('data.appointments.0.notes')->assertJsonMissingPath('data.appointments.0.patient_phone');
    $this->getJson($path.'?view=history')->assertOk()->assertJsonCount(2, 'data.appointments')
        ->assertJsonPath('data.appointments.0.id', $cancelled->id)->assertJsonPath('data.appointments.0.patient_actions', [])
        ->assertJsonPath('data.appointments.1.id', $past->id);
    $this->getJson($path.'/'.$previous->id)->assertOk()->assertJsonPath('data.id', $package->id)
        ->assertJsonPath('data.starts_at', '2026-12-04 11:00:00')->assertJsonMissingPath('data.patient_email');
});

test('patient appointment history is paginated in a stable order', function () {
    $this->travelTo(Carbon::parse('2026-12-04 05:00:00', 'UTC'));
    [$business, , $branch] = clinicAppointmentFixture();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $visit = [
        'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'completed',
        'starts_at' => '2026-12-03 10:00:00', 'ends_at' => '2026-12-03 10:30:00',
    ];
    $oldest = $patient->appointments()->create($visit);
    $patient->appointments()->createMany(array_fill(0, 12, $visit));

    $this->actingAs($patient->user, 'sanctum')->getJson('/api/patient/appointments?view=history&page=2')
        ->assertOk()->assertJsonPath('data.current_page', 2)->assertJsonPath('data.last_page', 2)
        ->assertJsonCount(1, 'data.appointments')->assertJsonPath('data.appointments.0.id', $oldest->id);
});

test('a replacement linked to a different offer does not hide a patient appointment from history', function () {
    $this->travelTo(Carbon::parse('2026-12-04 05:00:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment] = clinicAppointmentFixture();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    [$originalOffer, $otherOffer] = $conversation->offers()->createMany([
        ['title' => 'İlk teklif', 'business_id' => $business->id, 'patient_profile_id' => $patient->id,
            'created_by' => $owner->id, 'status' => 'accepted', 'amount' => 100],
        ['title' => 'Başka teklif', 'business_id' => $business->id, 'patient_profile_id' => $patient->id,
            'created_by' => $owner->id, 'status' => 'accepted', 'amount' => 200],
    ])->all();
    $visit = ['business_id' => $business->id, 'branch_id' => $branch->id, 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id];
    $original = $patient->appointments()->create($visit + [
        'offer_id' => $originalOffer->id, 'status' => 'rescheduled',
        'starts_at' => '2026-12-03 10:00:00', 'ends_at' => '2026-12-03 10:30:00',
    ]);
    $replacement = $patient->appointments()->create($visit + [
        'offer_id' => $otherOffer->id, 'status' => 'confirmed', 'rescheduled_from_appointment_id' => $original->id,
        'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00',
    ]);
    $path = '/api/patient/appointments';

    $this->actingAs($patient->user, 'sanctum')->getJson($path.'?view=history')->assertOk()
        ->assertJsonCount(1, 'data.appointments')->assertJsonPath('data.appointments.0.id', $original->id);
    $this->getJson($path.'/'.$original->id)->assertOk()->assertJsonPath('data.id', $original->id);
    $this->getJson($path)->assertOk()->assertJsonPath('data.appointments.0.id', $replacement->id);
});

test('clinic appointment creation keeps database failures out of both booking responses', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    config(['app.debug' => false]);
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $permission = Permission::create(['name' => 'appointments.create', 'display_name' => 'Randevu oluşturma', 'module' => 'appointments']);
    $owner->permissions()->attach($permission, ['business_id' => $business->id, 'is_allowed' => true]);
    Exceptions::fake();
    Appointment::creating(static function (): never {
        throw new QueryException('test', 'insert into appointments', [], new PDOException('Database write test marker'));
    });
    $request = ['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'patient_name' => 'Deneme Hastası',
        'starts_at' => '2026-12-04 09:00:00'];

    $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments', $request)
        ->assertInternalServerError()->assertDontSee('Database write test marker');
    $this->postJson('/api/appointments', $request + [
        'business_id' => $business->id, 'branch_id' => $branch->id, 'require_online_bookable' => false,
    ])
        ->assertInternalServerError()->assertDontSee('Database write test marker');

    $this->assertDatabaseCount('appointments', 0);
    Exceptions::assertReportedCount(2);
});

test('a database failure recording the second package session rolls the whole booking back', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    config(['app.debug' => false]);
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'İki seanslık paket', 'status' => 'accepted', 'amount' => 100,
        'package_snapshot' => ['branch_id' => $branch->id, 'treatments' => [['treatment_id' => $treatment->id, 'quantity' => 2]]],
    ]);
    Exceptions::fake();
    Mail::fake();
    Appointment::creating(static function (Appointment $appointment): void {
        if ($appointment->starts_at->toTimeString() === '10:00:00') {
            throw new QueryException('test', 'insert into appointments', [], new PDOException('Package write test marker'));
        }
    });
    $session = ['treatment_id' => $treatment->id, 'doctor_id' => $doctor->id, 'starts_at' => '2026-12-04 09:00:00'];

    $this->actingAs($owner, 'sanctum')->postJson($calendar.'/package-offers/'.$offer->id.'/appointments', [
        'sessions' => [$session, [...$session, 'starts_at' => '2026-12-04 10:00:00']],
    ])->assertInternalServerError()->assertDontSee('Package write test marker');

    $this->assertDatabaseCount('appointments', 0);
    $this->assertDatabaseCount('appointment_status_histories', 0);
    $this->assertDatabaseCount('notifications', 0);
    Mail::assertNothingQueued();
    Exceptions::assertReported(QueryException::class);
});

test('an appointment status stays pending when its status history cannot be saved', function () {
    config(['app.debug' => false]);
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $visit = $branch->appointments()->create(['business_id' => $business->id, 'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id, 'status' => 'pending', 'starts_at' => '2026-12-04 09:00:00', 'ends_at' => '2026-12-04 09:30:00']);
    Exceptions::fake();
    AppointmentStatusHistory::creating(static function (): never {
        throw new QueryException('test', 'insert into appointment_status_histories', [], new PDOException('Status history test marker'));
    });

    $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments/'.$visit->id.'/confirm')
        ->assertInternalServerError()->assertDontSee('Status history test marker');

    expect($visit->refresh()->status)->toBe('pending');
    Exceptions::assertReported(QueryException::class);
});

test('clinic appointment changes can be retried after an in-app notification failure', function (string $action, string $previousStatus, string $resultingStatus, string $notificationType) {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $patientUser = User::factory()->create();
    $patient = $patientUser->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $visit = $patient->appointments()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'doctor_id' => $doctor->id,
        'treatment_id' => $treatment->id, 'status' => $previousStatus,
        'starts_at' => '2026-12-04 09:00:00', 'ends_at' => '2026-12-04 09:30:00',
    ]);
    $url = $calendar.'/appointments/'.$visit->id.'/'.$action;
    $payload = match ($action) {
        'cancel' => ['cancellation_reason' => 'Klinik programı değişti.'],
        'reschedule' => ['starts_at' => '2026-12-04 10:00:00'],
        default => [],
    };
    $failNotification = true;

    Exceptions::fake();
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$failNotification): void {
        if ($failNotification && $event->channel === 'database') {
            throw new RuntimeException('Appointment change notification unavailable.');
        }
    });

    $this->actingAs($owner, 'sanctum')->postJson($url, $payload)->assertInternalServerError();

    expect($visit->refresh()->status)->toBe($previousStatus);
    $this->assertDatabaseCount('appointments', 1);
    $this->assertDatabaseCount('appointment_status_histories', 0);
    $this->assertDatabaseCount('notifications', 0);
    Exceptions::assertReported(RuntimeException::class);

    $failNotification = false;
    $currentId = $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.status', $resultingStatus)->json('data.id');

    $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $currentId, 'new_status' => $resultingStatus]);
    $notification = $patientUser->notifications()->sole();
    expect($notification->data['notification_type'])->toBe($notificationType)
        ->and($notification->data['appointment_id'])->toBe($currentId);

    $this->actingAs($patientUser, 'sanctum')->getJson('/api/patient/appointments/'.$visit->id)->assertOk()
        ->assertJsonPath('data.id', $currentId)
        ->assertJsonPath('data.status', $resultingStatus)
        ->assertJsonPath('data.starts_at', $payload['starts_at'] ?? '2026-12-04 09:00:00');
})->with([
    'confirm' => ['confirm', 'pending', 'confirmed', NotificationType::APPOINTMENT_CONFIRMED],
    'cancel' => ['cancel', 'confirmed', 'cancelled', NotificationType::APPOINTMENT_CANCELLED],
    'complete' => ['complete', 'confirmed', 'completed', NotificationType::APPOINTMENT_COMPLETED],
    'no show' => ['no-show', 'confirmed', 'no_show', NotificationType::APPOINTMENT_NO_SHOW],
    'reschedule' => ['reschedule', 'confirmed', 'confirmed', NotificationType::APPOINTMENT_RESCHEDULED],
]);

test('package booking and rescheduling respect a patients other active appointments', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $doctor->update(['is_public' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $branch->appointmentSetting()->update(['online_booking_enabled' => true]);
    $patientUser = User::factory()->create();
    $patient = $patientUser->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'İki seanslık paket', 'status' => 'accepted', 'amount' => 100,
        'package_snapshot' => ['branch_id' => $branch->id, 'treatments' => [['treatment_id' => $treatment->id, 'quantity' => 2]]],
    ]);
    $otherClinic = Branch::factory()->create();
    $otherDoctor = Doctor::factory()->for(Person::factory()->for($otherClinic->business)->for($otherClinic))->create();
    $patient->appointments()->create([
        'business_id' => $otherClinic->business_id, 'branch_id' => $otherClinic->id, 'doctor_id' => $otherDoctor->id,
        'status' => 'confirmed', 'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00',
        'notes' => 'Özel klinik notu',
    ]);
    $session = ['treatment_id' => $treatment->id, 'doctor_id' => $doctor->id, 'starts_at' => '2026-12-04 09:00:00'];
    $conflicting = [...$session, 'starts_at' => '2026-12-04 10:00:00'];
    $selections = ['branch_id' => $branch->id, 'treatment_id' => $treatment->id, 'date' => '2026-12-04'];
    $bookPackage = '/api/offers/'.$offer->id.'/appointments';
    $bookVisit = '/api/patient/appointments/booking-branches/'.$branch->id;

    $this->actingAs($owner, 'sanctum')->postJson($calendar.'/package-offers/'.$offer->id.'/appointments', [
        'sessions' => [$session, $conflicting],
    ])->assertUnprocessable()->assertJsonPath('message', 'Seçilen tarih ve saat için randevu müsait değil.')
        ->assertDontSee($otherClinic->name)->assertDontSee('Özel klinik notu');
    $this->assertDatabaseCount('appointments', 1);
    $this->assertDatabaseCount('appointment_status_histories', 0);

    $this->actingAs($patientUser, 'sanctum');
    $offerSlots = $this->getJson($bookPackage.'/options?'.http_build_query($selections))->assertOk()->json('data.doctors.0.slots');
    expect($offerSlots)->toContain('2026-12-04 10:30:00')->not->toContain('2026-12-04 10:00:00');
    $visitSlots = $this->getJson($bookVisit.'/slots?'.http_build_query($selections + ['doctor_id' => $doctor->id]))
        ->assertOk()->json('data.slots');
    expect($visitSlots)->toContain('2026-12-04 10:30:00')->not->toContain('2026-12-04 10:00:00');
    $this->postJson($bookVisit, $conflicting)->assertUnprocessable();

    $appointments = $this->postJson($bookPackage, [
        'branch_id' => $branch->id, 'sessions' => [$session, [...$session, 'starts_at' => '2026-12-04 10:30:00']],
    ])->assertCreated()->assertJsonCount(2, 'data.appointments')->json('data.appointments');
    $reschedule = '/api/patient/appointments/'.$appointments[0]['id'];
    $moveSlots = $this->getJson($reschedule.'/reschedule-options?date=2026-12-04')->assertOk()->json('data.slots');
    expect($moveSlots)->toContain('2026-12-04 09:30:00')->not->toContain('2026-12-04 10:00:00');
    $this->postJson($reschedule.'/reschedule', ['starts_at' => $conflicting['starts_at']])
        ->assertUnprocessable();
    $this->getJson('/api/patient/appointments')->assertOk()->assertJsonCount(3, 'data.appointments');

    $this->actingAs($owner, 'sanctum')->getJson($calendar.'?date=2026-12-04')
        ->assertOk()->assertJsonCount(2, 'data.appointments');
    $this->assertDatabaseCount('appointments', 3);
    $this->assertDatabaseCount('appointment_status_histories', 2);
});

test('patients can move and cancel their own package session from the offered schedule', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    Notification::fake();
    [$business, $owner, $branch, $otherBranch, $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $doctor->update(['is_public' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $branch->appointmentSetting()->update(['online_booking_enabled' => true, 'slot_interval_minutes' => 15]);
    $otherDoctor = Doctor::factory()->for(Person::factory()->for($business)->for($branch))->create();
    $patientUser = User::factory()->create();
    $patient = $patientUser->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'İki seanslık paket', 'status' => 'accepted', 'amount' => 100,
        'package_snapshot' => ['branch_id' => $branch->id, 'treatments' => [['treatment_id' => $treatment->id, 'quantity' => 2]]],
    ]);
    $visit = [
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'status' => 'confirmed',
        'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00', 'notes' => 'Eski randevu açıklaması',
    ];
    $appointment = $offer->appointments()->create($visit);
    $otherSession = $offer->appointments()->create([
        ...$visit, 'doctor_id' => $otherDoctor->id, 'starts_at' => '2026-12-04 11:00:00', 'ends_at' => '2026-12-04 11:30:00',
    ]);
    $otherStaff = User::factory()->create();
    $business->memberships()->create(['user_id' => $otherStaff->id, 'role' => 'staff', 'is_active' => true])
        ->branches()->attach($otherBranch->id, ['is_active' => true]);
    $schedule = '/api/offers/'.$offer->id.'/appointments?include_patient_actions=1';
    $action = '/api/patient/appointments/'.$appointment->id;
    $options = $action.'/reschedule-options?date=2026-12-04';

    $this->getJson($options)->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create(), 'sanctum')->getJson($options)->assertForbidden();
    $this->actingAs($otherStaff, 'sanctum')->getJson($options)->assertNotFound();
    $this->postJson($action.'/cancel')->assertNotFound();
    $this->actingAs($patientUser, 'sanctum')->getJson($schedule)->assertOk()
        ->assertJsonPath('data.0.patient_actions', ['cancel', 'reschedule']);
    $slots = $this->getJson($options)->assertOk()->json('data.slots');
    expect($slots)->toContain('2026-12-04 09:45:00')->not->toContain('2026-12-04 10:00:00', '2026-12-04 11:00:00');
    $this->postJson($action.'/reschedule', ['starts_at' => '2026-12-04 11:00:00'])->assertUnprocessable();
    $moved = $this->postJson($action.'/reschedule', ['starts_at' => '2026-12-04 09:45:00'])
        ->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonMissingPath('data.notes')->json('data.id');
    $this->postJson($action.'/reschedule', ['starts_at' => '2026-12-04 09:30:00'])->assertUnprocessable();
    $this->assertDatabaseHas('appointments', ['id' => $moved, 'rescheduled_from_appointment_id' => $appointment->id,
        'offer_id' => $offer->id, 'notes' => 'Eski randevu açıklaması']);
    $this->getJson($schedule)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $moved);

    $this->postJson('/api/patient/appointments/'.$moved.'/cancel', ['cancellation_reason' => 'Program değişikliği'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $moved, 'changed_by_user_id' => $patientUser->id,
        'new_status' => 'cancelled', 'reason' => 'Program değişikliği']);
    $this->assertDatabaseHas('appointments', ['id' => $otherSession->id, 'status' => 'confirmed']);
    $this->getJson($schedule)->assertOk()->assertJsonMissingPath('data.0.patient_actions');
    $this->actingAs($owner, 'sanctum')->getJson($calendar.'?date=2026-12-04')->assertOk()
        ->assertJsonPath('data.appointments.0.status', 'cancelled');
    Notification::assertSentTo($owner, PatientAppointmentChangedNotification::class,
        fn (PatientAppointmentChangedNotification $notification): bool => $notification->toArray($owner)['notification_type'] === NotificationType::APPOINTMENT_RESCHEDULED);
    Notification::assertSentTo($owner, PatientAppointmentChangedNotification::class,
        fn (PatientAppointmentChangedNotification $notification): bool => $notification->toArray($owner)['notification_type'] === NotificationType::APPOINTMENT_CANCELLED);
    Notification::assertNotSentTo($otherStaff, PatientAppointmentChangedNotification::class);
});

test('patient changes enforce enabled settings and Istanbul notice cutoffs without restricting clinic staff', function () {
    $this->travelTo(Carbon::parse('2026-12-04 05:00:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $doctor->update(['is_public' => true]);
    $branch->treatments()->updateExistingPivot($treatment->id, ['is_online_bookable' => true]);
    $settings = $branch->appointmentSetting;
    $settings->update(['online_booking_enabled' => true, 'rescheduling_enabled' => false, 'cancellation_before_minutes' => 121]);
    $appointment = Appointment::create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'status' => 'pending',
        'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00',
    ]);
    $path = '/api/patient/appointments/'.$appointment->id;

    $this->actingAs($patient->user, 'sanctum')->postJson($path.'/cancel', ['cancellation_reason' => 'Deneme'])->assertUnprocessable();
    $this->getJson($path.'/reschedule-options?date=2026-12-04')->assertUnprocessable();
    $this->postJson($path.'/reschedule', ['starts_at' => '2026-12-04 11:00:00'])->assertUnprocessable();
    $settings->update(['rescheduling_enabled' => true, 'rescheduling_before_minutes' => 121, 'cancellation_enabled' => false]);
    $this->postJson($path.'/cancel', ['cancellation_reason' => 'Deneme'])->assertUnprocessable();
    $this->postJson($path.'/reschedule', ['starts_at' => '2026-12-04 11:00:00'])->assertUnprocessable();
    $settings->update(['cancellation_enabled' => true, 'cancellation_before_minutes' => 120]);
    $this->postJson($path.'/cancel', ['cancellation_reason' => 'Sınırda iptal'])->assertOk();

    $past = $appointment->replicate()->fill(['starts_at' => '2026-12-04 08:00:00', 'ends_at' => '2026-12-04 08:30:00']);
    $past->save();
    $settings->update(['cancellation_before_minutes' => 0]);
    $this->postJson('/api/patient/appointments/'.$past->id.'/cancel', ['cancellation_reason' => 'Deneme'])->assertUnprocessable();
    $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments/'.$past->id.'/cancel', ['cancellation_reason' => 'Klinik iptali'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
});

test('patients see the current appointment when a legacy single offer was moved before replacements kept the link', function () {
    $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment] = clinicAppointmentFixture();
    $branch->appointmentSetting()->update(['is_active' => false, 'cancellation_enabled' => false]);
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $conversation = $patient->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $owner->id, 'title' => 'Tekil tedavi teklifi', 'status' => 'accepted', 'amount' => 100,
        'treatment_id' => $treatment->id,
    ]);
    $original = $offer->appointments()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'status' => 'rescheduled',
        'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00',
    ]);
    $current = $original->rescheduledAppointments()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'status' => 'confirmed',
        'starts_at' => '2026-12-11 10:00:00', 'ends_at' => '2026-12-11 10:30:00',
    ]);

    $this->actingAs($patient->user, 'sanctum')->getJson('/api/offers/'.$offer->id.'/appointments?include_patient_actions=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $current->id)
        ->assertJsonPath('data.0.patient_actions', ['cancel']);
    $this->postJson('/api/patient/appointments/'.$current->id.'/cancel', ['cancellation_reason' => 'Deneme'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
});

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
    Mail::assertQueued(NewPatientAppointmentMail::class, 2);

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

test('legacy rescheduling compares the same calendar time across accepted timestamp formats', function () {
    $this->travelTo(Carbon::parse('2026-12-03 21:30:00', 'UTC'));
    [$business, $owner, $branch, , $doctor, $treatment, $calendar] = clinicAppointmentFixture();
    $doctor->workingHours()->where('branch_id', $branch->id)->update(['end_time' => '14:00']);
    $permission = Permission::create(['name' => 'appointments.reschedule', 'display_name' => 'Randevu tarihini değiştir', 'module' => 'appointments']);
    $owner->permissions()->attach($permission->id, ['business_id' => $business->id, 'is_allowed' => true]);

    $id = $this->actingAs($owner, 'sanctum')->postJson($calendar.'/appointments', [
        'patient_name' => 'Örnek Hasta', 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id,
        'starts_at' => '2026-12-04 09:00:00',
    ])->assertCreated()->json('data.id');
    $reschedule = '/api/appointments/'.$id.'/reschedule';

    foreach (['2026-12-04T09:00:00+03:00', '2026-12-04T09:00:00.750Z'] as $sameTime) {
        $this->postJson($reschedule, ['starts_at' => $sameTime])->assertUnprocessable()
            ->assertJsonPath('message', 'Randevu için farklı bir tarih veya saat seçin.');
    }
    $this->assertDatabaseCount('appointments', 1);
    $this->assertDatabaseCount('appointment_status_histories', 1);
    $this->assertDatabaseHas('appointments', ['id' => $id, 'status' => 'pending']);

    $moved = $this->postJson($reschedule, ['starts_at' => '2026-12-04T12:00:00+03:00'])
        ->assertOk()->json('data.id');
    $this->getJson($calendar.'/appointments/'.$moved)->assertOk()
        ->assertJsonPath('data.starts_at', '2026-12-04 12:00:00')
        ->assertJsonPath('data.ends_at', '2026-12-04 12:30:00');
    $this->assertDatabaseHas('appointments', ['id' => $moved, 'rescheduled_from_appointment_id' => $id, 'starts_at' => '2026-12-04 12:00:00']);
    $this->assertDatabaseHas('appointments', ['id' => $id, 'status' => 'rescheduled']);
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
