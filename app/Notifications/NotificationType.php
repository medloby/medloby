<?php

namespace App\Notifications;

final class NotificationType
{
    public const NEW_APPOINTMENT = 'new_appointment';

    public const APPOINTMENT_CONFIRMED = 'appointment_confirmed';

    public const APPOINTMENT_CANCELLED = 'appointment_cancelled';

    public const APPOINTMENT_RESCHEDULED = 'appointment_rescheduled';

    public const NEW_OFFER = 'new_offer';

    public const OFFER_ACCEPTED = 'offer_accepted';

    public const OFFER_REJECTED = 'offer_rejected';

    public const NEW_MESSAGE = 'new_message';

    public const PATIENT_REQUEST = 'patient_request';

    public const SYSTEM_ANNOUNCEMENT = 'system_announcement';

    /**
     * Medloby'deki tüm bildirim türleri.
     */
    public static function all(): array
    {
        return [
            self::NEW_APPOINTMENT,
            self::APPOINTMENT_CONFIRMED,
            self::APPOINTMENT_CANCELLED,
            self::APPOINTMENT_RESCHEDULED,
            self::NEW_OFFER,
            self::OFFER_ACCEPTED,
            self::OFFER_REJECTED,
            self::NEW_MESSAGE,
            self::PATIENT_REQUEST,
            self::SYSTEM_ANNOUNCEMENT,
        ];
    }

    /**
     * Kullanıcıya gösterilecek bildirim isimleri.
     */
    public static function labels(): array
    {
        return [
            self::NEW_APPOINTMENT => 'Yeni randevu',
            self::APPOINTMENT_CONFIRMED => 'Randevu onaylandı',
            self::APPOINTMENT_CANCELLED => 'Randevu iptal edildi',
            self::APPOINTMENT_RESCHEDULED => 'Randevu tarihi değiştirildi',
            self::NEW_OFFER => 'Yeni teklif',
            self::OFFER_ACCEPTED => 'Teklif kabul edildi',
            self::OFFER_REJECTED => 'Teklif reddedildi',
            self::NEW_MESSAGE => 'Yeni mesaj',
            self::PATIENT_REQUEST => 'Hasta talebi',
            self::SYSTEM_ANNOUNCEMENT => 'Sistem duyuruları',
        ];
    }

    /**
     * Varsayılan uygulama içi bildirim ayarları.
     */
    public static function defaultInApp(): bool
    {
        return true;
    }

    /**
     * Varsayılan e-posta ayarları.
     *
     * Yeni randevu e-postası varsayılan olarak açık,
     * diğer bildirimler varsayılan olarak kapalıdır.
     */
    public static function defaultEmail(
        string $type
    ): bool {
        return $type === self::NEW_APPOINTMENT;
    }
}