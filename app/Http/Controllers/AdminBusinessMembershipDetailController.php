<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;

class AdminBusinessMembershipDetailController extends Controller
{
    /**
     * Bir işletme üyeliğinin detayını admin için gösterir.
     */
    public function show(
        Business $business,
        BusinessUser $businessUser
    ): JsonResponse {
        abort_unless(
            $businessUser->business_id === $business->id,
            404
        );

        $businessUser->load([
            'user:id,name,email,email_verified_at',
            'business:id,name,slug,status',
        ]);

        return response()->json([
            'success' => true,
            'data' => $businessUser,
        ]);
    }
}