<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminBusinessController extends Controller
{
    /**
     * Admin için bekleyen klinik başvurularını listeler.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->input('per_page', 20), 1),
            100
        );

        $businesses = Business::query()
            ->where('status', 'pending')
            ->with([
                'branches',
                'memberships.user',
                'contractAcceptances.platformContract',
            ])
            ->latest()
            ->paginate($perPage);

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
            'contractAcceptances.platformContract',
            'reviews.reviewedBy',
        ]);

        return response()->json([
            'success' => true,
            'data' => $business,
        ]);
    }

    /**
     * Bekleyen klinik başvurusunu onaylar.
     */
    public function approve(
        Request $request,
        Business $business
    ): JsonResponse {
        $admin = $request->user();

        $result = DB::transaction(function () use ($business, $admin) {
            $business->refresh();

            if ($business->status !== 'pending') {
                throw ValidationException::withMessages([
                    'business' => [
                        'Sadece bekleyen klinik başvuruları onaylanabilir.',
                    ],
                ]);
            }

            $hasAcceptedContract = $business
                ->contractAcceptances()
                ->where('is_accepted', true)
                ->exists();

            if (! $hasAcceptedContract) {
                throw ValidationException::withMessages([
                    'contract' => [
                        'Klinik başvurusu onaylanmadan önce geçerli bir sözleşme kabulü bulunmalıdır.',
                    ],
                ]);
            }

            $previousStatus = $business->status;

            $business->update([
                'status' => 'active',
                'is_verified' => true,
                'verified_at' => now(),
            ]);

            $business->branches()->update([
                'status' => 'active',
            ]);

            $review = BusinessReview::create([
                'business_id' => $business->id,
                'reviewed_by_user_id' => $admin->id,
                'decision' => 'approved',
                'reviewed_at' => now(),
                'notes' => 'Klinik başvurusu admin tarafından onaylandı.',
                'rejection_reason' => null,
                'previous_status' => $previousStatus,
                'new_status' => 'active',
            ]);

            return [
                'business' => $business->fresh([
                    'branches',
                    'contractAcceptances.platformContract',
                ]),
                'review' => $review,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Klinik başvurusu başarıyla onaylandı.',
            'data' => $result,
        ]);
    }

    /**
     * Bekleyen klinik başvurusunu reddeder.
     */
    public function reject(
        Request $request,
        Business $business
    ): JsonResponse {
        $validated = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'min:10',
                'max:5000',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $admin = $request->user();

        $result = DB::transaction(function () use (
            $business,
            $admin,
            $validated
        ) {
            $business->refresh();

            if ($business->status !== 'pending') {
                throw ValidationException::withMessages([
                    'business' => [
                        'Sadece bekleyen klinik başvuruları reddedilebilir.',
                    ],
                ]);
            }

            $previousStatus = $business->status;

            $business->update([
                'status' => 'rejected',
                'is_verified' => false,
                'verified_at' => null,
            ]);

            $business->branches()->update([
                'status' => 'rejected',
            ]);

            $review = BusinessReview::create([
                'business_id' => $business->id,
                'reviewed_by_user_id' => $admin->id,
                'decision' => 'rejected',
                'reviewed_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'rejection_reason' => $validated['rejection_reason'],
                'previous_status' => $previousStatus,
                'new_status' => 'rejected',
            ]);

            return [
                'business' => $business->fresh([
                    'branches',
                    'contractAcceptances.platformContract',
                ]),
                'review' => $review,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Klinik başvurusu reddedildi.',
            'data' => $result,
        ]);
    }
}