<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBusinessMemberRoleController extends Controller
{
    /**
     * Bir işletme üyesinin rolünü admin tarafından günceller.
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

        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                'max:50',
                'in:business_owner,manager,staff',
            ],
        ]);

        $oldRole = $businessUser->role;
        $newRole = $validated['role'];

        if ($oldRole === $newRole) {
            return response()->json([
                'success' => false,
                'message' => 'Üyenin rolü zaten bu rolde.',
            ], 422);
        }

        $businessUser->update([
            'role' => $newRole,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Üye rolü başarıyla güncellendi.',
            'data' => [
                'membership' => $businessUser->fresh([
                    'user:id,name,email',
                    'business:id,name,slug,status',
                ]),
                'previous_role' => $oldRole,
                'new_role' => $newRole,
            ],
        ]);
    }
}