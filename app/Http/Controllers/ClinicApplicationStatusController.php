<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicApplicationStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $applications = Business::query()
            ->whereHas('memberships', fn ($memberships) => $memberships
                ->where('user_id', $userId)
                ->where('role', 'business_owner')
                ->where('is_active', true))
            ->whereIn('status', ['pending', 'active', 'rejected', 'suspended'])
            ->with('latestApplicationReview')
            ->orderByDesc('id')
            ->get(['id', 'name', 'status', 'is_verified'])
            ->map(function (Business $business): ?array {
                $review = $business->latestApplicationReview;
                $status = match ($business->status) {
                    'pending', 'rejected' => $business->status,
                    default => $review?->decision === 'approved' ? 'approved' : null,
                };

                if ($status === null) {
                    return null;
                }

                if ($review?->decision !== $status) {
                    $review = null;
                }

                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'status' => $status,
                    'crm_available' => $status === 'approved' && $business->allowsCrmAccess(),
                    'reviewed_at' => $review?->reviewed_at?->toISOString(),
                    'applicant_rejection_reason' => $status === 'rejected' ? $review?->applicant_rejection_reason : null,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ])->header('Cache-Control', 'private, no-store');
    }
}
