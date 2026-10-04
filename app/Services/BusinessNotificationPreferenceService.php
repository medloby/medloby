<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessNotificationPreference;
use App\Notifications\NotificationType;
use Illuminate\Support\Collection;

class BusinessNotificationPreferenceService
{
    /**
     * İşletmenin tüm bildirim tercihlerini getirir.
     *
     * Veritabanında kayıt varsa onu kullanır.
     * Kayıt yoksa NotificationType üzerindeki varsayılan
     * değerleri kullanır.
     */
    public function getAll(Business $business): Collection
    {
        $preferences = $business->notificationPreferences()
            ->get()
            ->keyBy('notification_type');

        return collect(NotificationType::all())
            ->map(function (string $type) use ($preferences) {
                $preference = $preferences->get($type);

                return [
                    'notification_type' => $type,
                    'label' => NotificationType::labels()[$type],
                    'in_app_enabled' => $preference
                        ? (bool) $preference->in_app_enabled
                        : NotificationType::defaultInApp(),
                    'email_enabled' => $preference
                        ? (bool) $preference->email_enabled
                        : NotificationType::defaultEmail($type),
                ];
            })
            ->values();
    }

    /**
     * Belirli bir bildirim türünün tercihlerini getirir.
     */
    public function get(
        Business $business,
        string $type
    ): array {
        if (! in_array($type, NotificationType::all(), true)) {
            throw new \InvalidArgumentException(
                'Geçersiz bildirim türü.'
            );
        }

        $preference = $business->notificationPreferences()
            ->where('notification_type', $type)
            ->first();

        return [
            'notification_type' => $type,
            'label' => NotificationType::labels()[$type],
            'in_app_enabled' => $preference
                ? (bool) $preference->in_app_enabled
                : NotificationType::defaultInApp(),
            'email_enabled' => $preference
                ? (bool) $preference->email_enabled
                : NotificationType::defaultEmail($type),
        ];
    }

    /**
     * Bir bildirim türünün tercihlerini kaydeder veya günceller.
     */
    public function update(
        Business $business,
        string $type,
        bool $inAppEnabled,
        bool $emailEnabled
    ): BusinessNotificationPreference {
        if (! in_array($type, NotificationType::all(), true)) {
            throw new \InvalidArgumentException(
                'Geçersiz bildirim türü.'
            );
        }

        return BusinessNotificationPreference::updateOrCreate(
            [
                'business_id' => $business->id,
                'notification_type' => $type,
            ],
            [
                'in_app_enabled' => $inAppEnabled,
                'email_enabled' => $emailEnabled,
            ]
        );
    }

    /**
     * İşletmenin bütün bildirim tercihlerini varsayılan
     * değerlere geri döndürür.
     */
    public function resetToDefaults(
        Business $business
    ): Collection {
        $preferences = collect();

        foreach (NotificationType::all() as $type) {
            $preferences->push(
                $this->update(
                    $business,
                    $type,
                    NotificationType::defaultInApp(),
                    NotificationType::defaultEmail($type)
                )
            );
        }

        return $preferences;
    }

    /**
     * Bir bildirim türünün uygulama içi bildirim durumunu kontrol eder.
     */
    public function isInAppEnabled(
        Business $business,
        string $type
    ): bool {
        return $this->get(
            $business,
            $type
        )['in_app_enabled'];
    }

    /**
     * Bir bildirim türünün e-posta durumunu kontrol eder.
     */
    public function isEmailEnabled(
        Business $business,
        string $type
    ): bool {
        return $this->get(
            $business,
            $type
        )['email_enabled'];
    }
}