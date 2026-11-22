<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\Treatment;
use App\Models\WaitingList;
use App\Notifications\WaitingListAvailability;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AppointmentWaitingList
{
    public function __construct(
        private AppointmentAvailabilityService $booking,
        private AppointmentSlotAvailabilityService $availability,
    ) {}

    public function canWait(Branch $branch, Carbon $date): bool
    {
        $today = Appointment::clinicLocalNow()->startOfDay();
        if ($date->lt($today)) {
            return false;
        }

        $settings = $branch->appointmentSetting;
        if (! $settings?->is_active) {
            return true;
        }

        return ($settings->same_day_booking_enabled || ! $date->isSameDay($today))
            && $date->lte($today->addDays($settings->maximum_booking_days));
    }

    public function pendingRequest(PatientProfile $patient, Branch $branch, Doctor $doctor, Treatment $treatment, Carbon $date): ?WaitingList
    {
        return $patient->waitingLists()->onlineBooking()->where('status', 'waiting')
            ->where('business_id', $branch->business_id)->where('branch_id', $branch->id)
            ->where('doctor_id', $doctor->id)->where('treatment_id', $treatment->id)
            ->whereDate('preferred_date', $date->toDateString())->first();
    }

    public function join(PatientProfile $patient, Branch $branch, Doctor $doctor, Treatment $treatment, Carbon $date): WaitingList
    {
        return $this->booking->runBookingTransaction(function () use ($patient, $branch, $doctor, $treatment, $date): WaitingList {
            PatientProfile::query()->lockForUpdate()->findOrFail($patient->id);

            $existing = $this->pendingRequest($patient, $branch, $doctor, $treatment, $date);
            if ($existing) {
                return $existing;
            }

            if (! $this->canWait($branch, $date)) {
                throw ValidationException::withMessages(['date' => 'Randevuya açık bir gün seçin.']);
            }

            if ($this->availability->getAvailableSlots($doctor, $branch->id, $treatment, $date) !== []) {
                throw ValidationException::withMessages(['date' => 'Bu gün için uygun randevu saati var. Saatleri yeniden kontrol edin.']);
            }

            return $patient->waitingLists()->create([
                'business_id' => $branch->business_id,
                'branch_id' => $branch->id,
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'preferred_date' => $date->toDateString(),
                'source' => WaitingList::ONLINE_BOOKING,
                'status' => 'waiting',
            ]);
        });
    }

    public function sendAvailable(): int
    {
        $sent = 0;
        WaitingList::query()->onlineBooking()->where('status', 'waiting')
            ->where('preferred_date', '>=', Appointment::clinicLocalNow()->toDateString())
            ->whereHas('branch', fn (Builder $branch) => $branch->bookableOnline()->whereColumn('branches.business_id', 'waiting_lists.business_id'))
            ->whereHas('patientProfile.user', fn (Builder $user) => $user->whereNotNull('email_verified_at'))
            ->with(['branch.appointmentSetting', 'doctor.person', 'treatment', 'patientProfile.user'])
            ->chunkById(100, function (Collection $entries) use (&$sent): void {
                $groups = $entries->groupBy(fn (WaitingList $item): string => implode(':', [
                    $item->branch_id, $item->doctor_id, $item->treatment_id, $item->preferred_date->toDateString(),
                ]));

                foreach ($groups as $group) {
                    if (! $this->hasAvailableSlot($group->first())) {
                        continue;
                    }

                    foreach ($group as $request) {
                        $sent += (int) $this->notifyPatient($request);
                    }
                }
            });

        return $sent;
    }

    private function hasAvailableSlot(WaitingList $request): bool
    {
        $doctor = $request->doctor;
        $treatment = $request->treatment;

        return $request->branch !== null
            && $this->canWait($request->branch, $request->preferred_date)
            && $doctor?->is_public
            && $doctor->person?->business_id === $request->business_id
            && $treatment?->is_active
            && $this->availability->getAvailableSlots($doctor, $request->branch_id, $treatment, $request->preferred_date) !== [];
    }

    private function notifyPatient(WaitingList $request): bool
    {
        $patient = $request->patientProfile?->user;
        if (! $patient?->hasVerifiedEmail()) {
            return false;
        }

        return DB::transaction(function () use ($patient, $request): bool {
            $updated = WaitingList::query()->whereKey($request->id)->where('status', 'waiting')
                ->update(['status' => 'notified', 'notified_at' => now()]);
            if ($updated === 0) {
                return false;
            }

            $patient->notify(new WaitingListAvailability($request->id));

            return true;
        }, attempts: 3);
    }
}
