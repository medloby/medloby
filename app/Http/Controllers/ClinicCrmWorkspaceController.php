<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicCrmWorkspaceController extends Controller
{
    public function staffIndex(Request $request): JsonResponse
    {
        $workspaces = $request->user()->businessMemberships()
            ->withStaffCrmAccess()
            ->with('business:id,name')
            ->get()
            ->map(fn (BusinessUser $member) => $member->business->only(['id', 'name']))
            ->sortBy('name')->values();

        return response()->json(['success' => true, 'data' => $workspaces])
            ->header('Cache-Control', 'private, no-store');
    }

    public function __invoke(Request $request, Business $business): JsonResponse
    {
        $user = $request->user();
        $membership = $user->businessMemberships()
            ->where('business_id', $business->id)
            ->whereIn('role', ['business_owner', 'manager', 'staff'])
            ->where('is_active', true)
            ->first();

        abort_unless($membership, 403, 'Bu kliniğin CRM alanına erişim yetkiniz yok.');
        abort_unless($business->allowsCrmAccess(), 403, 'CRM erişimi için klinik onayı gereklidir.');

        $ownsClinic = $membership->role === 'business_owner';
        $branches = $business->branches()->where('status', 'active')
            ->when(! $ownsClinic, fn ($query) => $query->whereIn('id', $user->accessibleBranchIds($business->id)))
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);
        abort_if(! $ownsClinic && $branches->isEmpty(), 403, 'Bu kliniğin aktif şubelerine erişim yetkiniz yok.');

        $workspace = [
            'name' => $business->name,
            'branches' => $branches,
            'can_manage_operations' => $ownsClinic,
        ];
        if ($ownsClinic && ($plan = $business->membershipPlanLabel()) !== null) {
            $workspace['membership_plan'] = $plan;
        }

        return response()->json([
            'success' => true,
            'data' => $workspace,
        ])->header('Cache-Control', 'private, no-store');
    }
}
