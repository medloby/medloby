<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Notifications\NotificationType;
use App\Services\BusinessNotificationPreferenceService;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewAppointmentMail extends AppointmentCreatedMail
{
    protected function shouldSendToRecipient(Appointment $appointment): bool
    {
        $business = $appointment->business;

        return filled($business->email) && $this->hasTo($business->email)
            && app(BusinessNotificationPreferenceService::class)->isEmailEnabled($business, NotificationType::NEW_APPOINTMENT);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Medloby - Yeni Randevu Geldi',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointments.new',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
