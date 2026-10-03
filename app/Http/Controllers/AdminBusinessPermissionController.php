<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBusinessPermissionController extends Controller
{
    /**
     * Bir işletmedeki kullanıcı yetkilerini admin için listeler.
     */
    public function index(
        Request $request,
        Business $business
    ): JsonResponse {
        $permissions = $business->permissions()
            ->with([
                'user:id,name,email',
                'permission:id,name,display_name,module,description,is_active',
                'grantedBy:id,name,email',
            ])
            ->latest()
            ->paginate(
                min(
                    (int) $request->input('per_page', 20),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}