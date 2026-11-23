<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Support\Facades\DB;

final class ClinicMembershipAudit
{
    public function lockMember(Business $business, int $membershipId): BusinessUser
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite ignores row locks, so reserve its write lock before reading the previous state.
            DB::affectingStatement('UPDATE business_user SET id = id WHERE 1 = 0');
        }

        return $business->memberships()->lockForUpdate()->findOrFail($membershipId);
    }

    public function record(
        BusinessUser $member,
        int $actorId,
        string $action,
        ?string $previous,
        ?string $current,
        ?int $permissionId = null,
    ): void {
        if ($previous === $current) {
            return;
        }

        DB::table('clinic_membership_changes')->insert([
            'business_id' => $member->business_id,
            'business_user_id' => $member->id,
            'user_id' => $member->user_id,
            'actor_user_id' => $actorId,
            'permission_id' => $permissionId,
            'action' => $action,
            'previous_value' => $previous,
            'new_value' => $current,
            'created_at' => now(),
        ]);
    }
}
