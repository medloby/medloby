<?php

namespace App\Services;

use App\Models\User;

class CatalogManagementAccess
{
    public function authorizeManagement(User $user, ?int $businessId, ?int $branchId = null): void
    {
        $allowed = $businessId === null
            ? $user->is_admin
            : $user->businessMemberships()
                ->where('business_id', $businessId)
                ->where('role', 'business_owner')
                ->where('is_active', true)
                ->exists();

        if (! $allowed && $businessId !== null && $branchId !== null) {
            $allowed = $user->hasBusinessBranchAccess($businessId, $branchId)
                && $user->hasBusinessPermission($businessId, 'branches.manage');
        }

        abort_unless($allowed, 403, 'Bu kaydı yönetme yetkiniz yok.');
    }
}
