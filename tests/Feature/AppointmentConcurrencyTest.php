<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;

test('a patient and a clinic cannot reserve overlapping time for a shared doctor or patient', function (bool $samePatient) {
    $this->withConcurrentDatabase(function (string $directory, array $settings) use ($samePatient): void {
        $business = Business::factory()->create();
        $owner = User::factory()->create();
        $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
        $branch = Branch::factory()->for($business)->create();
        $otherBranch = Branch::factory()->for($business)->create();
        $person = Person::factory()->for($business)->for($branch)->create();
        $doctor = Doctor::factory()->for($person)->create(['is_public' => true]);
        $patientDoctor = $samePatient
            ? Doctor::factory()->for(Person::factory()->for($business)->for($otherBranch))->create(['is_public' => true])
            : $doctor;
        $treatment = Treatment::create([
            'treatment_category_id' => TreatmentCategory::factory()->create()->id,
            'name' => 'Randevu Muayenesi', 'slug' => 'randevu-muayenesi', 'is_active' => true, 'duration_minutes' => 30,
        ]);
        foreach ([[$branch, $doctor], [$otherBranch, $patientDoctor]] as [$assignedBranch, $assignedDoctor]) {
            $assignedDoctor->treatments()->syncWithoutDetaching([$treatment->id => ['is_active' => true]]);
            $assignedBranch->treatments()->attach($treatment->id, ['is_active' => true, 'is_online_bookable' => true]);
            $assignedDoctor->branches()->attach($assignedBranch->id, ['status' => 'active']);
            DoctorWorkingHour::create([
                'doctor_id' => $assignedDoctor->id, 'branch_id' => $assignedBranch->id,
                'day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '12:00', 'is_active' => true,
            ]);
        }
        $otherBranch->appointmentSetting()->create(['is_active' => true, 'online_booking_enabled' => true]);
        $patient = User::factory()->create();
        $profile = $patient->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
        if ($samePatient) {
            $profile->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
        }

        $slot = ['treatment_id' => $treatment->id, 'starts_at' => '2026-12-04 09:00:00'];
        $clinicPatient = $samePatient ? ['patient_profile_id' => $profile->id] : ['patient_name' => 'Klinik Hastası'];
        $results = $this->concurrentPostRequests($directory, $settings, Appointment::class, [
            'user_id' => $owner->id,
            'path' => "/api/branches/{$branch->id}/clinic-calendar/appointments",
            'payload' => $slot + ['doctor_id' => $doctor->id] + $clinicPatient,
        ], [
            'user_id' => $patient->id,
            'path' => "/api/patient/appointments/booking-branches/{$otherBranch->id}",
            'payload' => $slot + ['doctor_id' => $patientDoctor->id],
        ]);

        expect($results['first'][0])->toBe(201)
            ->and($results['second'][0])->toBe(422)
            ->and($results['second'][1]['message'])->toBe('Seçilen tarih ve saat için randevu müsait değil.');
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('appointments', [
            'id' => $results['first'][1]['data']['id'], 'doctor_id' => $doctor->id, 'branch_id' => $branch->id,
            'patient_profile_id' => $samePatient ? $profile->id : null,
        ]);
        $this->assertDatabaseCount('appointment_status_histories', 1);
    });
})->with(['same doctor' => [false], 'same patient with different doctors' => [true]]);
