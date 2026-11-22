<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\PatientProfile;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use App\Notifications\NotificationType;
use App\Services\AppointmentReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function createReminderAppointment(Branch $branch, ?PatientProfile $patient, array $attributes = []): Appointment
{
    return Appointment::create(array_replace([
        'branch_id' => $branch->id, 'business_id' => $branch->business_id, 'patient_profile_id' => $patient?->id,
        'starts_at' => '2026-12-05 23:30:00', 'ends_at' => '2026-12-06 00:00:00', 'status' => 'confirmed',
        'patient_email' => 'hasta@example.test', 'notes' => 'Özel randevu notu',
    ], $attributes));
}

test('the scheduled reminder reaches only the verified patient of a confirmed visit within the next 24 hours', function () {
    $this->travelTo(Carbon::parse('2026-12-04 20:30:00', 'UTC'));
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    $patient = $user->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $unverified = User::factory()->unverified()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $other = User::factory()->create();
    $inactive = Branch::factory()->for($branch->business)->create(['status' => 'inactive']);
    $suspended = Branch::factory()->create();
    $suspended->business->update(['status' => 'suspended']);

    $due = createReminderAppointment($branch, $patient);
    $justOutside = createReminderAppointment($branch, $patient, ['starts_at' => '2026-12-05 23:31:00']);
    createReminderAppointment($branch, $patient, ['starts_at' => '2026-12-04 23:30:00', 'ends_at' => '2026-12-05 00:00:00']);
    foreach (['pending', 'cancelled', 'rescheduled', 'completed', 'no_show'] as $status) {
        createReminderAppointment($branch, $patient, ['status' => $status]);
    }
    createReminderAppointment($inactive, $patient);
    createReminderAppointment($suspended, $patient);
    createReminderAppointment($branch, $unverified);
    createReminderAppointment($branch, null);

    $this->artisan('medloby:remind-appointments')->assertSuccessful();
    $this->artisan('medloby:remind-appointments')->assertSuccessful();

    $this->assertDatabaseCount('notifications', 1);
    expect($due->fresh()->reminded_at)->not->toBeNull();
    expect($justOutside->fresh()->reminded_at)->toBeNull();
    $this->actingAs($user, 'sanctum')->getJson('/api/notifications/unread')->assertOk()->assertJsonPath('data.data.0.data', [
        'notification_type' => NotificationType::APPOINTMENT_REMINDER,
        'title' => 'Randevu hatırlatması',
        'message' => 'Yaklaşan randevunuzun bilgilerini Randevularım ekranından görüntüleyebilirsiniz.',
        'appointment_id' => $due->id,
    ]);
    $this->getJson('/api/patient/appointments/'.$due->id)->assertOk()->assertJsonPath('data.id', $due->id);
    $this->actingAs($other, 'sanctum')->getJson('/api/notifications/unread')->assertOk()->assertJsonCount(0, 'data.data');
    $this->getJson('/api/patient/appointments/'.$due->id)->assertNotFound();

    $this->travel(1)->minute();
    $this->artisan('medloby:remind-appointments')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 2);
    expect($user->notifications()->pluck('data')->pluck('appointment_id')->all())->toContain($due->id, $justOutside->id);
});

test('rescheduling gives the replacement appointment its own reminder at the new time', function () {
    $this->travelTo(Carbon::parse('2026-12-01 07:00:00', 'UTC'));
    $branch = Branch::factory()->create();
    $owner = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $doctor = Doctor::factory()->for(Person::factory()->for($branch->business)->for($branch))->create();
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Deneme muayenesi', 'slug' => 'deneme-muayenesi', 'is_active' => true, 'duration_minutes' => 30,
    ]);
    $branch->treatments()->attach($treatment->id, ['is_active' => true]);
    $doctor->branches()->attach($branch->id, ['status' => 'active']);
    $doctor->treatments()->attach($treatment->id, ['is_active' => true]);
    DoctorWorkingHour::create([
        'doctor_id' => $doctor->id, 'branch_id' => $branch->id, 'day_of_week' => 4,
        'start_time' => '09:00', 'end_time' => '12:00', 'is_active' => true,
    ]);
    $appointment = createReminderAppointment($branch, $patient, [
        'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id,
        'starts_at' => '2026-12-02 10:00:00', 'ends_at' => '2026-12-02 10:30:00',
    ]);
    $reminders = fn () => $patient->user->notifications()->where('type', AppointmentReminder::class)->get()->pluck('data.appointment_id')->all();
    $this->artisan('medloby:remind-appointments')->assertSuccessful();
    expect($reminders())->toBe([$appointment->id]);

    $moved = $this->actingAs($owner, 'sanctum')->postJson("/api/branches/{$branch->id}/clinic-calendar/appointments/{$appointment->id}/reschedule", [
        'starts_at' => '2026-12-03 10:00:00',
    ])->assertOk()->assertJsonMissingPath('data.reminded_at')->json('data.id');
    $this->artisan('medloby:remind-appointments')->assertSuccessful();
    expect($reminders())->toBe([$appointment->id]);
    $this->assertDatabaseHas('appointments', ['id' => $moved, 'reminded_at' => null]);

    $this->travel(1)->day();
    $this->artisan('medloby:remind-appointments')->assertSuccessful();
    $this->artisan('medloby:remind-appointments')->assertSuccessful();
    expect($reminders())->toHaveCount(2)->toContain($appointment->id, $moved);
    $this->actingAs($patient->user, 'sanctum')->getJson('/api/patient/appointments/'.$appointment->id)
        ->assertOk()->assertJsonPath('data.id', $moved);
});

test('a failed reminder write releases the delivery marker for the next run', function () {
    $this->travelTo(Carbon::parse('2026-12-04 20:30:00', 'UTC'));
    $branch = Branch::factory()->create();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hastası']);
    $appointment = createReminderAppointment($branch, $patient);
    Event::listen(NotificationSending::class, function (NotificationSending $event): void {
        if ($event->notification instanceof AppointmentReminder) {
            throw new RuntimeException('Deneme bildirim yazma hatası');
        }
    });

    expect(fn () => app(AppointmentReminderService::class)->sendDue())->toThrow(RuntimeException::class, 'Deneme bildirim yazma hatası');
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'reminded_at' => null]);
    $this->assertDatabaseCount('notifications', 0);

    Event::forget(NotificationSending::class);
    $this->artisan('medloby:remind-appointments')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 1);
});
