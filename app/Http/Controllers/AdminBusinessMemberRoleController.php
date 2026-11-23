<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Services\ClinicMembershipAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBusinessMemberRoleController extends Controller
{
    /**
     * Bir işletme üyesinin rolünü admin tarafından günceller.
     */
    public function update(
        Request $request,
        Business $business,
        BusinessUser $businessUser,
        ClinicMembershipAudit $audit,
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

        $newRole = $validated['role'];
        $actorId = $request->user()->id;
        [$businessUser, $oldRole] = DB::transaction(function () use ($business, $businessUser, $audit, $newRole, $actorId): array {
            $member = $audit->lockMember($business, $businessUser->id);
            $oldRole = $member->role;
            abort_if($newRole === 'business_owner' && $member->user?->staff_business_id !== null,
                422, 'Klinik tarafından oluşturulan personel hesabı işletme sahibi yapılamaz.');

            if ($oldRole !== $newRole) {
                $member->update(['role' => $newRole]);
                $audit->record($member, $actorId, 'member_role_changed', $oldRole, $newRole);
            }

            return [$member, $oldRole];
        }, attempts: 3);

        if ($oldRole === $newRole) {
            return response()->json([
                'success' => false,
                'message' => 'Üyenin rolü zaten bu rolde.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Üye rolü başarıyla güncellendi.',
            'data' => [
                'membership' => $businessUser->load([
                    'user:id,name,email',
                    'business:id,name,slug,status',
                ]),
                'previous_role' => $oldRole,
                'new_role' => $newRole,
            ],
        ]);
    }
}
