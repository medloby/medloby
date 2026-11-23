<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OfferAcceptedNotification;
use App\Notifications\OfferRejectedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;

class NotificationService
{
    /**
     * Kullanıcının bildirimlerini sayfalı olarak getirir.
     */
    public function paginate(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $user->notifications()
            ->latest()
            ->paginate($perPage)
            ->through($this->withoutPrivateOfferDetails(...));
    }

    /**
     * Kullanıcının okunmamış bildirimlerini getirir.
     */
    public function unread(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $user->unreadNotifications()
            ->latest()
            ->paginate($perPage)
            ->through($this->withoutPrivateOfferDetails(...));
    }

    /**
     * Kullanıcının okunmamış bildirim sayısını getirir.
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Tek bir bildirimi okundu olarak işaretler.
     */
    public function markAsRead(
        User $user,
        string $notificationId
    ): DatabaseNotification {
        $notification = $user->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $this->withoutPrivateOfferDetails($notification);
    }

    /**
     * Kullanıcının tüm okunmamış bildirimlerini okundu yapar.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);
    }

    /**
     * Tek bir bildirimi kullanıcıdan siler.
     */
    public function delete(
        User $user,
        string $notificationId
    ): void {
        $notification = $user->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->delete();
    }

    private function withoutPrivateOfferDetails(DatabaseNotification $notification): DatabaseNotification
    {
        if (in_array($notification->type, [OfferAcceptedNotification::class, OfferRejectedNotification::class], true)) {
            $notification->data = Arr::except($notification->data, ['patient_profile_id', 'treatment_id', 'rejection_reason']);
        }

        return $notification;
    }
}
