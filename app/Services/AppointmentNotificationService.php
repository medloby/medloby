<?php

namespace App\Services;

use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use App\Notifications\NotificationType;
use Illuminate\Support\Facades\Mail;

class AppointmentNotificationService
{
    public function __construct(
        protected BusinessNotificationPreferenceService $preferenceService
    ) {
    }

    /**
     * Yeni randevu için işletmeye gerekli bildirimleri gönderir.
     *
     * Başlangıçta:
     * - Medloby içi bildirim tercihe bağlıdır.
     * - E-posta tercihe bağlıdır.
     *
     * E-posta yalnızca işletmenin kayıtlı e-posta adresine gönderilir.
     */
    public function notifyNewAppointment(
        Appointment $appointment
    ): void {
        $business = $appointment->business;

        if (! $business) {
            return;
        }

        /*
         * Yeni randevu için işletme içi bildirim.
         *
         * Şimdilik sadece e-posta akışını kullanacağız.
         * Medloby içi randevu bildirimi daha sonra ayrıca
         * ilgili işletme kullanıcılarına yönlendirilecek.
         */

        if (
            $this->preferenceService->isEmailEnabled(
                $business,
                NotificationType::NEW_APPOINTMENT
            )
            && filled($business->email)
        ) {
            $appointment->loadMissing([
                'business',
                'branch',
                'patientProfile',
                'doctor.person',
                'treatment',
                'offer',
            ]);

            Mail::to($business->email)
                ->send(
                    new NewAppointmentMail($appointment)
                );
        }
    }
}