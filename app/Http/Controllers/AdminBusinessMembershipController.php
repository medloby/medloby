<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBusinessMembershipController extends Controller
{
    /**
     * Bir işletmenin üyelerini admin için listeler.
     */
    public function index(
        Request $request,
        Business $business
    ): JsonResponse {
        $memberships = $business->memberships()
            ->with([
                'user:id,name,email,email_verified_at',
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
            'data' => $memberships,
        ]);
    }
}