<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewPatientAppointmentMail extends AppointmentCreatedMail
{
    protected function shouldSendToRecipient(Appointment $appointment): bool
    {
        $patient = $appointment->patientProfile?->user;

        return $patient?->hasVerifiedEmail() && filled($patient->email) && $this->hasTo($patient->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Medloby - Randevunuz Oluşturuldu',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointments.patient-new',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
