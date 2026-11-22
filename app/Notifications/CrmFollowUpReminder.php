<?php

namespace App\Notifications;

use App\Models\CrmFollowUp;
use Illuminate\Notifications\Notification;

class CrmFollowUpReminder extends Notification
{
    public function __construct(private CrmFollowUp $followUp) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'crm_follow_up_due',
            'title' => 'CRM takip saati geldi',
            'message' => 'Planlanan takibi CRM’de görüntüleyebilirsiniz.',
            'branch_id' => $this->followUp->branch_id,
            'crm_follow_up_id' => $this->followUp->id,
            'scheduled_at' => $this->followUp->scheduled_at->toISOString(),
        ];
    }
}
