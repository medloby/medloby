<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Treatment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchTreatmentController extends Controller
{
    public function index(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $query = $branch->treatments()
            ->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->wherePivot('is_active', true);
        }

        if ($request->boolean('online_bookable')) {
            $query->wherePivot('is_online_bookable', true);
        }

        if ($request->boolean('offer_enabled')) {
            $query->wherePivot('is_offer_enabled', true);
        }

        $treatments = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branch->id,
                'treatments' => $treatments,
            ],
        ]);
    }

    public function store(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $validated = $request->validate([
            'treatment_id' => [
                'required',
                'integer',
                'exists:treatments,id',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'is_online_bookable' => [
                'sometimes',
                'boolean',
            ],
            'is_offer_enabled' => [
                'sometimes',
                'boolean',
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $treatment = Treatment::findOrFail(
            $validated['treatment_id']
        );

        if (
            $branch->treatments()
                ->where('treatments.id', $treatment->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'treatment_id' => [
                    'Bu tedavi zaten bu şubeye eklenmiş.',
                ],
            ]);
        }

        $branch->treatments()->attach(
            $treatment->id,
            [
                'is_active' => $validated['is_active'] ?? true,
                'is_online_bookable' =>
                    $validated['is_online_bookable'] ?? false,
                'is_offer_enabled' =>
                    $validated['is_offer_enabled'] ?? true,
                'duration_minutes' =>
                    $validated['duration_minutes'] ?? null,
            ]
        );

        $attachedTreatment = $branch->treatments()
            ->where('treatments.id', $treatment->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $attachedTreatment,
        ], 201);
    }

    public function update(
        Request $request,
        Branch $branch,
        Treatment $treatment
    ): JsonResponse {
        $this->ensureAttached(
            $branch,
            $treatment
        );

        $validated = $request->validate([
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'is_online_bookable' => [
                'sometimes',
                'boolean',
            ],
            'is_offer_enabled' => [
                'sometimes',
                'boolean',
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $branch->treatments()->updateExistingPivot(
            $treatment->id,
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $branch->treatments()
                ->where('treatments.id', $treatment->id)
                ->first(),
        ]);
    }

    public function deactivate(
        Branch $branch,
        Treatment $treatment
    ): JsonResponse {
        $this->ensureAttached(
            $branch,
            $treatment
        );

        $branch->treatments()->updateExistingPivot(
            $treatment->id,
            [
                'is_active' => false,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $branch->treatments()
                ->where('treatments.id', $treatment->id)
                ->first(),
        ]);
    }

    private function ensureAttached(
        Branch $branch,
        Treatment $treatment
    ): void {
        $exists = $branch->treatments()
            ->where('treatments.id', $treatment->id)
            ->exists();

        if (!$exists) {
            abort(
                404,
                'Bu tedavi bu şubeye tanımlı değil.'
            );
        }
    }
}