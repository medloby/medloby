<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\TreatmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TreatmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Treatment::query()
            ->with('treatmentCategory')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->boolean('online_bookable')) {
            $query->where('is_online_bookable', true);
        }

        if ($request->boolean('offer_enabled')) {
            $query->where('is_offer_enabled', true);
        }

        if ($request->filled('treatment_category_id')) {
            $query->where(
                'treatment_category_id',
                $request->integer('treatment_category_id')
            );
        }

        $treatments = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'treatments' => $treatments,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'treatment_category_id' => [
                'required',
                'integer',
                'exists:treatment_categories,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:treatments,slug',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'preparation' => [
                'nullable',
                'string',
            ],
            'aftercare' => [
                'nullable',
                'string',
            ],
            'included_services' => [
                'nullable',
                'string',
            ],
            'excluded_services' => [
                'nullable',
                'string',
            ],
            'image' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_online_bookable' => [
                'sometimes',
                'boolean',
            ],
            'is_offer_enabled' => [
                'sometimes',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $category = TreatmentCategory::findOrFail(
            $validated['treatment_category_id']
        );

        if (! $category->is_active) {
            abort(
                422,
                'Treatment category is not active.'
            );
        }

        $treatment = Treatment::create([
            'treatment_category_id' => $category->id,
            'name' => $validated['name'],
            'slug' => $validated['slug']
                ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'preparation' => $validated['preparation'] ?? null,
            'aftercare' => $validated['aftercare'] ?? null,
            'included_services' => $validated['included_services'] ?? null,
            'excluded_services' => $validated['excluded_services'] ?? null,
            'image' => $validated['image'] ?? null,
            'is_online_bookable' =>
                $validated['is_online_bookable'] ?? false,
            'is_offer_enabled' =>
                $validated['is_offer_enabled'] ?? true,
            'is_active' =>
                $validated['is_active'] ?? false,
            'sort_order' =>
                $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatment->fresh('treatmentCategory'),
        ], 201);
    }

    public function show(Treatment $treatment): JsonResponse
    {
        $treatment->load([
            'treatmentCategory',
            'branches',
            'doctors',
            'packages',
            'prices',
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatment,
        ]);
    }

    public function update(
        Request $request,
        Treatment $treatment
    ): JsonResponse {
        $validated = $request->validate([
            'treatment_category_id' => [
                'sometimes',
                'integer',
                'exists:treatment_categories,id',
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('treatments', 'slug')
                    ->ignore($treatment->id),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'preparation' => [
                'nullable',
                'string',
            ],
            'aftercare' => [
                'nullable',
                'string',
            ],
            'included_services' => [
                'nullable',
                'string',
            ],
            'excluded_services' => [
                'nullable',
                'string',
            ],
            'image' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_online_bookable' => [
                'sometimes',
                'boolean',
            ],
            'is_offer_enabled' => [
                'sometimes',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        if (array_key_exists(
            'treatment_category_id',
            $validated
        )) {
            $category = TreatmentCategory::findOrFail(
                $validated['treatment_category_id']
            );

            if (! $category->is_active) {
                abort(
                    422,
                    'Treatment category is not active.'
                );
            }
        }

        $treatment->update($validated);

        return response()->json([
            'success' => true,
            'data' => $treatment->fresh('treatmentCategory'),
        ]);
    }

    public function deactivate(
        Treatment $treatment
    ): JsonResponse {
        $treatment->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatment->fresh(),
        ]);
    }
}