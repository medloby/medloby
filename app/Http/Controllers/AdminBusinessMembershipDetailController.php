<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class AdminBusinessMembershipDetailController extends Controller
{
    /**
     * Bir işletme üyeliğinin detayını admin için gösterir.
     */
    public function show(
        Business $business,
        BusinessUser $businessUser
    ): JsonResponse {
        abort_unless(
            $businessUser->business_id === $business->id,
            404
        );

        $businessUser->load([
            'user:id,name,email,email_verified_at,login_code,staff_business_id',
            'business:id,name,slug,status',
            'branches' => fn ($query) => $query->where('branches.business_id', $business->id)
                ->orderBy('branches.name')->select('branches.id', 'branches.name', 'branches.status'),
        ]);

        $assignments = $business->permissions()->where('user_id', $businessUser->user_id)->pluck('is_allowed', 'permission_id');
        $permissions = Permission::query()->where('is_active', true)->orWhereIn('id', $assignments->keys())
            ->orderBy('module')->orderBy('display_name')->orderBy('id')
            ->get(['id', 'display_name', 'module', 'is_active'])
            ->map(fn (Permission $permission) => $permission->toArray() + ['is_allowed' => $assignments->get($permission->id)]);

        return response()->json([
            'success' => true,
            'data' => $businessUser->toArray() + ['permissions' => $permissions],
        ])->header('Cache-Control', 'private, no-store');
    }
}
