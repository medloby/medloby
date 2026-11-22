<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBusinessMemberStatusController extends Controller
{
    /**
     * Bir işletme üyesini aktif veya pasif yapar.
     */
    public function update(
        Request $request,
        Business $business,
        BusinessUser $businessUser
    ): JsonResponse {
        abort_unless(
            $businessUser->business_id === $business->id,
            404
        );

        $validated = $request->validate(['is_active' => ['sometimes', 'required', 'boolean']]);

        [$businessUser, $oldStatus] = DB::transaction(function () use ($business, $businessUser, $validated): array {
            $member = $business->memberships()->lockForUpdate()->findOrFail($businessUser->id);
            $oldStatus = $member->is_active;
            $member->update(['is_active' => (bool) ($validated['is_active'] ?? ! $oldStatus)]);

            if (! $member->is_active && $member->user?->staff_business_id === $business->id) {
                $member->user()->lockForUpdate()->firstOrFail()->tokens()->delete();
            }

            return [$member, $oldStatus];
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'message' => $businessUser->is_active
                ? 'Üye başarıyla aktifleştirildi.'
                : 'Üye başarıyla pasifleştirildi.',
            'data' => [
                'membership' => $businessUser->fresh([
                    'user:id,name,email',
                    'business:id,name,slug,status',
                ]),
                'previous_is_active' => $oldStatus,
                'is_active' => $businessUser->is_active,
            ],
        ]);
    }
}
