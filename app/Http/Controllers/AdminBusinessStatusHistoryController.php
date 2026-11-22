<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBusinessStatusHistoryController extends Controller
{
    /**
     * Bir kliniğin durum değişiklik geçmişini listeler.
     */
    public function index(
        Request $request,
        Business $business
    ): JsonResponse {
        $histories = $business->statusHistories()
            ->with([
                'changedBy:id,name,email',
            ])
            ->latest('changed_at')
            ->orderByDesc('id')
            ->paginate(
                min(
                    max((int) $request->input('per_page', 20), 1),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $histories,
        ])->header('Cache-Control', 'private, no-store');
    }
}
