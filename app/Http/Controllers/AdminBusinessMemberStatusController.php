<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;

class AdminBusinessMemberStatusController extends Controller
{
    /**
     * Bir işletme üyesini aktif veya pasif yapar.
     */
    public function update(
        Business $business,
        BusinessUser $businessUser
    ): JsonResponse {
        abort_unless(
            $businessUser->business_id === $business->id,
            404
        );

        $oldStatus = $businessUser->is_active;

        $businessUser->update([
            'is_active' => ! $oldStatus,
        ]);

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