<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Permission;
use App\Services\ClinicMembershipAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBusinessMemberPermissionController extends Controller
{
    /**
     * Bir işletme üyesine belirli bir yetkiyi verir veya kaldırır.
     */
    public function update(
        Request $request,
        Business $business,
        BusinessUser $businessUser,
        Permission $permission,
        ClinicMembershipAudit $audit,
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
        $actorId = $request->user()->id;

        [$businessUser, $businessUserPermission] = DB::transaction(function () use ($business, $businessUser, $permission, $audit, $actorId, $isAllowed): array {
            $member = $audit->lockMember($business, $businessUser->id);
            $assignment = $business->permissions()->firstOrNew([
                'user_id' => $member->user_id,
                'permission_id' => $permission->id,
            ]);
            $previous = $assignment->exists ? $this->permissionValue($assignment->is_allowed) : null;
            $assignment->fill(['granted_by_user_id' => $actorId, 'is_allowed' => $isAllowed])->save();
            $audit->record($member, $actorId, 'member_permission_changed', $previous, $this->permissionValue($isAllowed), $permission->id);

            return [$member, $assignment];
        }, attempts: 3);

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

    public function destroy(
        Request $request,
        Business $business,
        BusinessUser $businessUser,
        Permission $permission,
        ClinicMembershipAudit $audit,
    ): JsonResponse {
        abort_unless($businessUser->business_id === $business->id, 404);
        $actorId = $request->user()->id;
        DB::transaction(function () use ($business, $businessUser, $permission, $audit, $actorId): void {
            $member = $audit->lockMember($business, $businessUser->id);
            $assignment = $business->permissions()->where('user_id', $member->user_id)->where('permission_id', $permission->id)->first();
            if ($assignment === null) {
                return;
            }

            $assignment->delete();
            $audit->record($member, $actorId, 'member_permission_changed', $this->permissionValue($assignment->is_allowed), null, $permission->id);
        }, attempts: 3);

        return response()->json(['success' => true, 'message' => 'İzin varsayılan ayarına döndürüldü.', 'data' => null]);
    }

    private function permissionValue(bool $allowed): string
    {
        return $allowed ? 'allowed' : 'denied';
    }
}
