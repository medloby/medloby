<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CrmPipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmPipelineStageController extends Controller
{
    public function index(
        Branch $branch
    ): JsonResponse {
        $stages = CrmPipelineStage::query()
            ->where('business_id', $branch->business_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stages,
        ]);
    }

    public function store(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
            ],
            'color' => [
                'nullable',
                'string',
                'max:50',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'is_won' => [
                'sometimes',
                'boolean',
            ],
            'is_lost' => [
                'sometimes',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $slug = $validated['slug']
            ?? str()->slug($validated['name']);

        $baseSlug = $slug;
        $counter = 1;

        while (
            CrmPipelineStage::query()
                ->where('business_id', $branch->business_id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $stage = CrmPipelineStage::create([
            'business_id' => $branch->business_id,
            'name' => $validated['name'],
            'slug' => $slug,
            'color' => $validated['color'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_won' => $validated['is_won'] ?? false,
            'is_lost' => $validated['is_lost'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $stage->fresh([
                'business',
            ]),
        ], 201);
    }

    public function show(
        Branch $branch,
        CrmPipelineStage $crmPipelineStage
    ): JsonResponse {
        if (
            $crmPipelineStage->business_id
            !== $branch->business_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu pipeline aşaması belirtilen işletmeye ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $crmPipelineStage->load([
                'business',
            ]),
        ]);
    }

    public function update(
        Request $request,
        Branch $branch,
        CrmPipelineStage $crmPipelineStage
    ): JsonResponse {
        if (
            $crmPipelineStage->business_id
            !== $branch->business_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu pipeline aşaması belirtilen işletmeye ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'color' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'is_won' => [
                'sometimes',
                'boolean',
            ],
            'is_lost' => [
                'sometimes',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if (array_key_exists('slug', $validated)) {
            $duplicate = CrmPipelineStage::query()
                ->where('business_id', $branch->business_id)
                ->where('slug', $validated['slug'])
                ->where(
                    'id',
                    '!=',
                    $crmPipelineStage->id
                )
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu işletmede aynı slug zaten kullanılıyor.',
                ], 422);
            }
        }

        $crmPipelineStage->update($validated);

        return response()->json([
            'success' => true,
            'data' => $crmPipelineStage->fresh([
                'business',
            ]),
        ]);
    }

    public function deactivate(
        Branch $branch,
        CrmPipelineStage $crmPipelineStage
    ): JsonResponse {
        if (
            $crmPipelineStage->business_id
            !== $branch->business_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu pipeline aşaması belirtilen işletmeye ait değil.',
            ], 404);
        }

        $crmPipelineStage->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $crmPipelineStage->fresh(),
        ]);
    }
}