<?php

namespace App\Http\Controllers;

use App\Models\BookingCalendarOverride;
use App\Models\BookingCalendarRule;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingCalendarController extends Controller
{
    /**
     * Şubenin mevcut booking calendar ayarlarını getirir.
     */
    public function show(Request $request, Branch $branch): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessBranch($user, $branch)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        $rule = BookingCalendarRule::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->first();

        $overrides = BookingCalendarOverride::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branch->id,
                'default_status' => $rule?->default_status ?? 'open',
                'is_active' => $rule?->is_active ?? true,
                'overrides' => $overrides,
            ],
        ]);
    }

    /**
     * Şubenin varsayılan takvim durumunu oluşturur veya günceller.
     */
    public function updateRule(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $user = $request->user();

        if (! $this->canAccessBranch($user, $branch)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        $validated = $request->validate([
            'default_status' => [
                'required',
                Rule::in([
                    'open',
                    'closed',
                ]),
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $rule = BookingCalendarRule::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
            ],
            [
                'default_status' => $validated['default_status'],
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Randevu takvimi varsayılan ayarı güncellendi.',
            'data' => $rule,
        ]);
    }

    /**
     * Belirli bir tarih veya tarih aralığı için
     * özel takvim durumu oluşturur.
     *
     * status:
     * open   = açık
     * closed = kapalı
     */
    public function createOverride(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $user = $request->user();

        if (! $this->canAccessBranch($user, $branch)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        $validated = $request->validate([
            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
            'status' => [
                'required',
                Rule::in([
                    'open',
                    'closed',
                ]),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $override = BookingCalendarOverride::create([
            'branch_id' => $branch->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'reason' => $validated['reason'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Takvim özel tarih ayarı oluşturuldu.',
            'data' => $override,
        ], 201);
    }

    /**
     * Aktif özel takvim kayıtlarını listeler.
     */
    public function overrides(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $user = $request->user();

        if (! $this->canAccessBranch($user, $branch)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        $overrides = BookingCalendarOverride::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $overrides,
        ]);
    }

    /**
     * Özel takvim kaydını pasifleştirir.
     *
     * Kaydı fiziksel olarak silmiyoruz.
     * Geçmiş kayıtların korunması için soft-deactivate mantığı kullanıyoruz.
     */
    public function deactivateOverride(
        Request $request,
        Branch $branch,
        BookingCalendarOverride $override
    ): JsonResponse {
        $user = $request->user();

        if (! $this->canAccessBranch($user, $branch)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        if ($override->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Takvim kaydı bu şubeye ait değil.',
            ], 404);
        }

        $override->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Takvim özel tarih ayarı pasifleştirildi.',
            'data' => $override->fresh(),
        ]);
    }

    /**
     * Belirli bir override kaydını günceller.
     */
    public function updateOverride(
        Request $request,
        Branch $branch,
        BookingCalendarOverride $override
    ): JsonResponse {
        $user = $request->user();

        if (! $this->canAccessBranch($user, $branch)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        if ($override->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Takvim kaydı bu şubeye ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
            'status' => [
                'required',
                Rule::in([
                    'open',
                    'closed',
                ]),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $override->update([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'reason' => $validated['reason'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Takvim özel tarih ayarı güncellendi.',
            'data' => $override->fresh(),
        ]);
    }

    /**
     * Kullanıcının ilgili şubeye erişimi var mı?
     *
     * İşletme sahibi / yetkili kullanıcı:
     * Business üyeliği üzerinden kontrol edilir.
     *
     * Hasta:
     * Takvim yönetimi yapamaz.
     */
    protected function canAccessBranch(
        $user,
        Branch $branch
    ): bool {
        if (! $user) {
            return false;
        }

        return $user->businessMemberships()
            ->where('business_id', $branch->business_id)
            ->where('is_active', true)
            ->exists()
            && $user->hasBusinessBranchAccess(
                (int) $branch->business_id,
                (int) $branch->id
            );
    }
}