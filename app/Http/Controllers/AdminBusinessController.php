<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBusinessController extends Controller
{
    /**
     * Admin için bekleyen klinik başvurularını listeler.
     */
    public function index(Request $request): JsonResponse
    {
        $businesses = Business::query()
            ->where('status', 'pending')
            ->with([
                'branches',
                'memberships.user',
                'contractAcceptances',
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
            'data' => $businesses,
        ]);
    }

    /**
     * Admin için tek bir klinik başvurusunun detaylarını gösterir.
     */
    public function show(Business $business): JsonResponse
    {
        $business->load([
            'branches',
            'memberships.user',
            'contractAcceptances',
            'reviews.reviewedBy',
        ]);

        return response()->json([
            'success' => true,
            'data' => $business,
        ]);
    }
}