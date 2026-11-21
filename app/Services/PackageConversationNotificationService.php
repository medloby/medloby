<?php

namespace App\Services;

use App\Models\Conversation;
use App\Notifications\NotificationType;
use App\Notifications\PackageConversationNotification;

class PackageConversationNotificationService
{
    public function __construct(private BusinessNotificationPreferenceService $preferences) {}

    public function notifyRequest(Conversation $conversation, int $senderId): void
    {
        $this->notifyClinic($conversation, $senderId, NotificationType::PATIENT_REQUEST);
    }

    public function notifyMessage(Conversation $conversation, int $senderId): void
    {
        if ($conversation->treatment_package_id === null) {
            return;
        }

        $patientUser = $conversation->patientProfile?->user;
        if ($patientUser?->id === $senderId) {
            $this->notifyClinic($conversation, $senderId, NotificationType::NEW_MESSAGE);

            return;
        }

        $patientUser?->notify(new PackageConversationNotification($conversation, NotificationType::NEW_MESSAGE));
    }

    private function notifyClinic(Conversation $conversation, int $senderId, string $type): void
    {
        $business = $conversation->business;
        if (! $this->preferences->isInAppEnabled($business, $type)) {
            return;
        }

        $members = $business->memberships()
            ->where('is_active', true)
            ->where('user_id', '!=', $senderId)
            ->when($conversation->branch_id !== null, fn ($query) => $query
                ->where(fn ($members) => $members->where('role', 'business_owner')
                    ->orWhereHas('branches', fn ($branches) => $branches
                        ->whereKey($conversation->branch_id)
                        ->where('branches.status', 'active')
                        ->where('business_user_branch.is_active', true))))
            ->with('user')
            ->get();

        $notification = new PackageConversationNotification($conversation, $type);
        foreach ($members as $member) {
            $member->user?->notify($notification);
        }
    }
}
