<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminBusinessStatusController extends Controller
{
    /**
     * Aktif bir kliniği askıya alır.
     */
    public function suspend(
        Request $request,
        Business $business
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ]);

        $admin = $request->user();

        $result = DB::transaction(function () use (
            $business,
            $admin,
            $validated
        ) {
            $business->refresh();

            if ($business->status !== 'active') {
                throw ValidationException::withMessages([
                    'business' => [
                        'Yalnızca aktif klinikler askıya alınabilir.',
                    ],
                ]);
            }

            $previousStatus = $business->status;

            $business->update([
                'status' => 'suspended',
            ]);

            BusinessStatusHistory::create([
                'business_id' => $business->id,
                'changed_by_user_id' => $admin->id,
                'previous_status' => $previousStatus,
                'new_status' => 'suspended',
                'reason' => $validated['reason'],
                'changed_at' => now(),
            ]);

            return $business->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Klinik askıya alındı.',
            'data' => $result,
        ]);
    }

    /**
     * Askıya alınmış bir kliniği tekrar aktifleştirir.
     */
    public function reactivate(
        Request $request,
        Business $business
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ]);

        $admin = $request->user();

        $result = DB::transaction(function () use (
            $business,
            $admin,
            $validated
        ) {
            $business->refresh();

            if ($business->status !== 'suspended') {
                throw ValidationException::withMessages([
                    'business' => [
                        'Yalnızca askıya alınmış klinikler tekrar aktifleştirilebilir.',
                    ],
                ]);
            }

            $previousStatus = $business->status;

            $business->update([
                'status' => 'active',
            ]);

            BusinessStatusHistory::create([
                'business_id' => $business->id,
                'changed_by_user_id' => $admin->id,
                'previous_status' => $previousStatus,
                'new_status' => 'active',
                'reason' => $validated['reason'],
                'changed_at' => now(),
            ]);

            return $business->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Klinik tekrar aktifleştirildi.',
            'data' => $result,
        ]);
    }
}