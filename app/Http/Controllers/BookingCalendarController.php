<?php

namespace App\Http\Controllers;

use App\Models\BookingCalendarOverride;
use App\Models\BookingCalendarRule;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingCalendarController extends Controller
{
    /**
     * Şubenin mevcut booking calendar ayarlarını getirir.
     */
    public function show(Branch $branch): JsonResponse
    {
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
    public function overrides(Branch $branch): JsonResponse
    {
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
        Branch $branch,
        BookingCalendarOverride $override
    ): JsonResponse {
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
}
