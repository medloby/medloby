<?php

namespace App\Services;

use App\Models\User;

class PackageCatalogAccess
{
    public function authorizeManagement(User $user, ?int $businessId): void
    {
        $allowed = $businessId === null
            ? $user->is_admin
            : $user->businessMemberships()
                ->where('business_id', $businessId)
                ->where('role', 'business_owner')
                ->where('is_active', true)
                ->exists();

        abort_unless($allowed, 403, 'Bu kaydı yönetme yetkiniz yok.');
    }
}
