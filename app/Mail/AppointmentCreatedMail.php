<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;

abstract class AppointmentCreatedMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        #[WithoutRelations] public Appointment $appointment
    ) {}

    /**
     * @param  Factory|Mailer  $mailer
     */
    final public function send($mailer): ?SentMessage
    {
        $appointment = $this->appointment->fresh(['business', 'branch']);
        if (! $appointment || ! in_array($appointment->status, ['pending', 'confirmed'], true)
            || ! $appointment->starts_at?->gt(Appointment::clinicLocalNow())
            || ! $appointment->business?->allowsCrmAccess()
            || $appointment->branch?->status !== 'active'
            || $appointment->branch->business_id !== $appointment->business_id
            || count($this->to) !== 1 || ! $this->shouldSendToRecipient($appointment)) {
            return null;
        }

        $this->appointment = $appointment;

        return parent::send($mailer);
    }

    abstract protected function shouldSendToRecipient(Appointment $appointment): bool;
}
