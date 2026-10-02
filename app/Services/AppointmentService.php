<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorWorkingHour;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function isTimeSlotAvailable(
        int $businessId,
        int $branchId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $doctorId = null
    ): bool {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return false;
        }

        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->first();

        if (! $branch) {
            return false;
        }

        if ($doctorId !== null) {
            $doctorExists = Doctor::query()
                ->whereKey($doctorId)
                ->where('status', 'active')
                ->exists();

            if (! $doctorExists) {
                return false;
            }

            $doctorWorksAtBranch = $branch->doctors()
                ->where('doctors.id', $doctorId)
                ->wherePivot('status', 'active')
                ->exists();

            if (! $doctorWorksAtBranch) {
                return false;
            }

            if ($this->doctorIsOnLeave(
                $doctorId,
                $branchId,
                $startsAt,
                $endsAt
            )) {
                return false;
            }

            if ($this->doctorIsOutsideWorkingHours(
                $doctorId,
                $branchId,
                $startsAt,
                $endsAt
            )) {
                return false;
            }
        }

        if ($this->hasCalendarBlock(
            $businessId,
            $branchId,
            $doctorId,
            $startsAt,
            $endsAt
        )) {
            return false;
        }

        if ($this->hasAppointmentConflict(
            $branchId,
            $doctorId,
            $startsAt,
            $endsAt
        )) {
            return false;
        }

        return true;
    }

    public function createAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $startsAt = Carbon::parse($data['starts_at']);
            $endsAt = Carbon::parse($data['ends_at']);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'Randevu bitiş zamanı başlangıç zamanından sonra olmalıdır.',
                ]);
            }

            if (
                isset($data['treatment_id']) &&
                ! $this->treatmentIsBookable(
                    (int) $data['branch_id'],
                    (int) $data['treatment_id']
                )
            ) {
                throw ValidationException::withMessages([
                    'treatment_id' => 'Seçilen tedavi bu şubede online randevuya açık değil.',
                ]);
            }

            if (
                isset($data['doctor_id']) &&
                isset($data['treatment_id']) &&
                ! $this->doctorCanPerformTreatment(
                    (int) $data['doctor_id'],
                    (int) $data['treatment_id']
                )
            ) {
                throw ValidationException::withMessages([
                    'doctor_id' => 'Seçilen doktor bu tedaviyi uygulamıyor.',
                ]);
            }

            $available = $this->isTimeSlotAvailable(
                businessId: (int) $data['business_id'],
                branchId: (int) $data['branch_id'],
                startsAt: $startsAt,
                endsAt: $endsAt,
                doctorId: isset($data['doctor_id'])
                    ? (int) $data['doctor_id']
                    : null,
            );

            if (! $available) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Seçilen tarih ve saat için randevu uygun değil.',
                ]);
            }

            $data['starts_at'] = $startsAt;
            $data['ends_at'] = $endsAt;

            $appointment = Appointment::create($data);

            $appointment->statusHistories()->create([
                'changed_by_user_id' => $data['created_by_user_id'] ?? null,
                'old_status' => null,
                'new_status' => $appointment->status,
                'reason' => 'Randevu oluşturuldu.',
                'changed_at' => now(),
            ]);

            return $appointment->load([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
            ]);
        });
    }

    public function changeStatus(
        Appointment $appointment,
        string $newStatus,
        ?int $userId = null,
        ?string $reason = null,
        ?string $notes = null
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $newStatus,
            $userId,
            $reason,
            $notes
        ) {
            $oldStatus = $appointment->status;

            if ($oldStatus === $newStatus) {
                return $appointment->load('statusHistories');
            }

            $appointment->update([
                'status' => $newStatus,
            ]);

            $appointment->statusHistories()->create([
                'changed_by_user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
            ]);
        });
    }

    public function cancelAppointment(
        Appointment $appointment,
        ?int $userId = null,
        ?string $reason = null,
        ?string $notes = null
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $userId,
            $reason,
            $notes
        ) {
            $appointment->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId,
            ]);

            $appointment->statusHistories()->create([
                'changed_by_user_id' => $userId,
                'old_status' => $appointment->getOriginal('status'),
                'new_status' => 'cancelled',
                'reason' => $reason,
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
            ]);
        });
    }

    public function rescheduleAppointment(
        Appointment $appointment,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $userId = null,
        ?string $reason = null
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $startsAt,
            $endsAt,
            $userId,
            $reason
        ) {
            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'Randevu bitiş zamanı başlangıç zamanından sonra olmalıdır.',
                ]);
            }

            $available = $this->isTimeSlotAvailable(
                businessId: (int) $appointment->business_id,
                branchId: (int) $appointment->branch_id,
                startsAt: $startsAt,
                endsAt: $endsAt,
                doctorId: $appointment->doctor_id
                    ? (int) $appointment->doctor_id
                    : null,
            );

            if (! $available) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Yeni tarih ve saat için randevu uygun değil.',
                ]);
            }

            $newAppointment = $appointment->replicate();

            $newAppointment->starts_at = $startsAt;
            $newAppointment->ends_at = $endsAt;
            $newAppointment->status = 'pending';
            $newAppointment->rescheduled_from_appointment_id = $appointment->id;
            $newAppointment->rescheduled_at = now();

            $newAppointment->save();

            $appointment->update([
                'status' => 'rescheduled',
                'rescheduled_at' => now(),
            ]);

            $appointment->statusHistories()->create([
                'changed_by_user_id' => $userId,
                'old_status' => $appointment->getOriginal('status'),
                'new_status' => 'rescheduled',
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            $newAppointment->statusHistories()->create([
                'changed_by_user_id' => $userId,
                'old_status' => null,
                'new_status' => 'pending',
                'reason' => 'Randevu yeniden planlandı.',
                'notes' => $reason,
                'changed_at' => now(),
            ]);

            return $newAppointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'rescheduledFrom',
                'statusHistories',
            ]);
        });
    }

    protected function hasAppointmentConflict(
        int $branchId,
        ?int $doctorId,
        Carbon $startsAt,
        Carbon $endsAt
    ): bool {
        return Appointment::query()
            ->where('branch_id', $branchId)
            ->whereNotIn('status', [
                'cancelled',
                'rejected',
                'rescheduled',
            ])
            ->where(function ($query) use ($doctorId) {
                if ($doctorId === null) {
                    $query->whereNull('doctor_id');
                } else {
                    $query->whereNull('doctor_id')
                        ->orWhere('doctor_id', $doctorId);
                }
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    protected function hasCalendarBlock(
        int $businessId,
        int $branchId,
        ?int $doctorId,
        Carbon $startsAt,
        Carbon $endsAt
    ): bool {
        return CalendarBlock::query()
            ->where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(function ($query) use ($doctorId) {
                if ($doctorId === null) {
                    $query->whereNull('doctor_id');
                } else {
                    $query->whereNull('doctor_id')
                        ->orWhere('doctor_id', $doctorId);
                }
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    protected function doctorIsOnLeave(
        int $doctorId,
        int $branchId,
        Carbon $startsAt,
        Carbon $endsAt
    ): bool {
        return DoctorLeave::query()
            ->where('doctor_id', $doctorId)
            ->where('is_approved', true)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->whereDate('start_date', '<=', $endsAt->toDateString())
            ->whereDate('end_date', '>=', $startsAt->toDateString())
            ->exists();
    }

    protected function doctorIsOutsideWorkingHours(
        int $doctorId,
        int $branchId,
        Carbon $startsAt,
        Carbon $endsAt
    ): bool {
        $dayOfWeek = $startsAt->dayOfWeekIso;

        $workingHours = DoctorWorkingHour::query()
            ->where('doctor_id', $doctorId)
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        if ($workingHours->isEmpty()) {
            return true;
        }

        $startTime = $startsAt->format('H:i:s');
        $endTime = $endsAt->format('H:i:s');

        foreach ($workingHours as $workingHour) {
            if (
                $startTime >= $workingHour->start_time &&
                $endTime <= $workingHour->end_time
            ) {
                return false;
            }
        }

        return true;
    }

    public function treatmentIsBookable(
        int $branchId,
        int $treatmentId
    ): bool {
        return Branch::query()
            ->whereKey($branchId)
            ->whereHas('treatments', function ($query) use ($treatmentId) {
                $query->where('treatments.id', $treatmentId)
                    ->where('branch_treatment.is_active', true)
                    ->where('branch_treatment.is_online_bookable', true);
            })
            ->exists();
    }

    public function doctorCanPerformTreatment(
        int $doctorId,
        int $treatmentId
    ): bool {
        return Doctor::query()
            ->whereKey($doctorId)
            ->whereHas('treatments', function ($query) use ($treatmentId) {
                $query->where('treatments.id', $treatmentId)
                    ->where('doctor_treatment.is_active', true);
            })
            ->exists();
    }
}