<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

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
            ->paginate($perPage);
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
            ->paginate($perPage);
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

        return $notification->fresh();
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
}