<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessUserPermission;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBusinessMemberPermissionController extends Controller
{
    /**
     * Bir işletme üyesine belirli bir yetkiyi verir veya kaldırır.
     */
    public function update(
        Request $request,
        Business $business,
        BusinessUser $businessUser,
        Permission $permission
    ): JsonResponse {
        abort_unless(
            $businessUser->business_id === $business->id,
            404
        );

        $validated = $request->validate([
            'is_allowed' => [
                'required',
                'boolean',
            ],
        ]);

        $isAllowed = (bool) $validated['is_allowed'];

        $businessUserPermission = BusinessUserPermission::updateOrCreate(
            [
                'business_id' => $business->id,
                'user_id' => $businessUser->user_id,
                'permission_id' => $permission->id,
            ],
            [
                'granted_by_user_id' => $request->user()->id,
                'is_allowed' => $isAllowed,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $isAllowed
                ? 'Üye yetkisi başarıyla verildi.'
                : 'Üye yetkisi başarıyla kaldırıldı.',
            'data' => [
                'permission' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'module' => $permission->module,
                    'description' => $permission->description,
                    'is_active' => $permission->is_active,
                ],
                'membership' => [
                    'id' => $businessUser->id,
                    'business_id' => $businessUser->business_id,
                    'user_id' => $businessUser->user_id,
                    'role' => $businessUser->role,
                    'is_active' => $businessUser->is_active,
                ],
                'permission_assignment' => [
                    'id' => $businessUserPermission->id,
                    'business_id' => $businessUserPermission->business_id,
                    'user_id' => $businessUserPermission->user_id,
                    'permission_id' => $businessUserPermission->permission_id,
                    'granted_by_user_id' => $businessUserPermission->granted_by_user_id,
                    'is_allowed' => $businessUserPermission->is_allowed,
                ],
            ],
        ]);
    }
}