<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AppointmentReminderService
{
    private const int HOURS_BEFORE_APPOINTMENT = 24;

    public function sendDue(): int
    {
        $sent = 0;

        foreach ($this->dueAppointments(Appointment::clinicLocalNow())->select('id')->lazyById(200) as $candidate) {
            $sent += (int) $this->sendReminder($candidate->id);
        }

        return $sent;
    }

    private function dueAppointments(Carbon $localNow): Builder
    {
        return Appointment::query()->where('status', 'confirmed')->whereNull('reminded_at')
            ->where('starts_at', '>', $localNow)
            ->where('starts_at', '<=', $localNow->copy()->addHours(self::HOURS_BEFORE_APPOINTMENT))
            ->whereHas('business', fn (Builder $business) => $business->where('status', 'active')->where('is_verified', true))
            ->whereHas('branch', fn (Builder $branch) => $branch->where('status', 'active')
                ->whereColumn('branches.business_id', 'appointments.business_id'))
            ->whereHas('patientProfile.user', fn (Builder $user) => $user->whereNotNull('email_verified_at'));
    }

    private function sendReminder(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $appointment = $this->dueAppointments(Appointment::clinicLocalNow())
                ->with(['patientProfile:id,user_id', 'patientProfile.user:id,email_verified_at'])->lockForUpdate()->find($id);
            $patient = $appointment?->patientProfile?->user;
            if (! $patient?->hasVerifiedEmail()) {
                return false;
            }

            $marked = Appointment::query()->whereKey($id)->whereNull('reminded_at')->update(['reminded_at' => now()]);
            if ($marked === 0) {
                return false;
            }

            $patient->notify(new AppointmentReminder($id));

            return true;
        }, attempts: 3);
    }
}
