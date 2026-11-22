<?php

namespace App\Services;

use App\Models\User;

class CatalogManagementAccess
{
    public function authorizeManagement(User $user, ?int $businessId, ?int $branchId = null): void
    {
        abort_unless($this->canManage($user, $businessId, $branchId), 403, 'Bu kaydı yönetme yetkiniz yok.');
    }

    public function canManage(User $user, ?int $businessId, ?int $branchId = null): bool
    {
        $allowed = $businessId === null
            ? (bool) $user->is_admin
            : $user->businessMemberships()
                ->where('business_id', $businessId)
                ->where('role', 'business_owner')
                ->where('is_active', true)
                ->exists();

        if (! $allowed && $businessId !== null && $branchId !== null) {
            $allowed = $user->hasBusinessBranchAccess($businessId, $branchId)
                && $user->hasBusinessPermission($businessId, 'branches.manage');
        }

        return $allowed;
    }
}
