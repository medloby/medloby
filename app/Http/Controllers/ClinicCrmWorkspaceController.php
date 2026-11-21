<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicCrmWorkspaceController extends Controller
{
    public function __invoke(Request $request, Business $business): JsonResponse
    {
        $ownsClinic = $request->user()->businessMemberships()
            ->where('business_id', $business->id)
            ->where('role', 'business_owner')
            ->where('is_active', true)
            ->exists();

        abort_unless($ownsClinic, 403, 'Bu kliniğin CRM alanına erişim yetkiniz yok.');
        abort_unless($business->allowsCrmAccess(), 403, 'CRM erişimi için klinik onayı gereklidir.');

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $business->name,
                'branches' => $business->branches()
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get(['id', 'name']),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
