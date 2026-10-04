<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessNotificationPreference;
use App\Notifications\NotificationType;
use App\Services\BusinessNotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BusinessNotificationPreferenceController extends Controller
{
    public function __construct(
        protected BusinessNotificationPreferenceService $preferenceService
    ) {
    }

    /**
     * İşletmenin bildirim tercihlerini listeler.
     */
    public function index(
        Request $request,
        Business $business
    ): JsonResponse {
        $this->authorizeOwner(
            $request,
            $business
        );

        return response()->json([
            'success' => true,
            'data' => $this->preferenceService->getAll(
                $business
            ),
        ]);
    }

    /**
     * Tek bir bildirim tercihinin ayarlarını günceller.
     */
    public function update(
        Request $request,
        Business $business,
        string $type
    ): JsonResponse {
        $this->authorizeOwner(
            $request,
            $business
        );

        if (! in_array(
            $type,
            NotificationType::all(),
            true
        )) {
            abort(
                422,
                'Geçersiz bildirim türü.'
            );
        }

        $validated = $request->validate([
            'in_app_enabled' => [
                'required',
                'boolean',
            ],
            'email_enabled' => [
                'required',
                'boolean',
            ],
        ]);

        try {
            $preference = $this->preferenceService->update(
                $business,
                $type,
                (bool) $validated['in_app_enabled'],
                (bool) $validated['email_enabled']
            );
        } catch (InvalidArgumentException $exception) {
            abort(
                422,
                $exception->getMessage()
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Bildirim tercihi güncellendi.',
            'data' => [
                'notification_type' => $preference->notification_type,
                'label' => NotificationType::labels()[
                    $preference->notification_type
                ],
                'in_app_enabled' =>
                    (bool) $preference->in_app_enabled,
                'email_enabled' =>
                    (bool) $preference->email_enabled,
            ],
        ]);
    }

    /**
     * İşletmenin tüm bildirim tercihlerini varsayılanlara döndürür.
     */
    public function reset(
        Request $request,
        Business $business
    ): JsonResponse {
        $this->authorizeOwner(
            $request,
            $business
        );

        $this->preferenceService->resetToDefaults(
            $business
        );

        return response()->json([
            'success' => true,
            'message' => 'Bildirim tercihleri varsayılan ayarlara döndürüldü.',
            'data' => $this->preferenceService->getAll(
                $business
            ),
        ]);
    }

    /**
     * Kullanıcının bu işletmenin aktif sahibi olup olmadığını
     * kontrol eder.
     */
    protected function authorizeOwner(
        Request $request,
        Business $business
    ): void {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isOwner = $user->businessMemberships()
            ->where('business_id', $business->id)
            ->where('role', 'business_owner')
            ->where('is_active', true)
            ->exists();

        if (! $isOwner) {
            abort(
                403,
                'Bu işletmenin bildirim ayarlarını yönetme yetkiniz yok.'
            );
        }
    }
}