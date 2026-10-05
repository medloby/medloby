<?php

namespace App\Http\Controllers;

use App\Models\TreatmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TreatmentCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TreatmentCategory::query()
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->integer('parent_id'));
        }

        $categories = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => [
                'nullable',
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
                'unique:treatment_categories,slug',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'image' => [
                'nullable',
                'string',
                'max:255',
            ],
            'icon' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if (
            isset($validated['parent_id']) &&
            !$this->parentExistsAsValidParent($validated['parent_id'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz üst kategori.',
            ], 422);
        }

        $category = TreatmentCategory::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $validated['slug']
                ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
        ], 201);
    }

    public function show(TreatmentCategory $treatmentCategory): JsonResponse
    {
        $treatmentCategory->load([
            'parent',
            'children',
            'treatments',
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatmentCategory,
        ]);
    }

    public function update(
        Request $request,
        TreatmentCategory $treatmentCategory
    ): JsonResponse {
        $validated = $request->validate([
            'parent_id' => [
                'nullable',
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
                Rule::unique('treatment_categories', 'slug')
                    ->ignore($treatmentCategory->id),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'image' => [
                'nullable',
                'string',
                'max:255',
            ],
            'icon' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if (
            array_key_exists('parent_id', $validated) &&
            $validated['parent_id'] !== null
        ) {
            if (
                !$this->parentExistsAsValidParent(
                    $validated['parent_id'],
                    $treatmentCategory->id
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geçersiz üst kategori.',
                ], 422);
            }
        }

        $treatmentCategory->update($validated);

        return response()->json([
            'success' => true,
            'data' => $treatmentCategory->fresh(),
        ]);
    }

    public function deactivate(
        TreatmentCategory $treatmentCategory
    ): JsonResponse {
        $treatmentCategory->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatmentCategory->fresh(),
        ]);
    }

    private function parentExistsAsValidParent(
        int $parentId,
        ?int $currentCategoryId = null
    ): bool {
        if (
            $currentCategoryId !== null &&
            $parentId === $currentCategoryId
        ) {
            return false;
        }

        $parent = TreatmentCategory::find($parentId);

        if (!$parent) {
            return false;
        }

        return true;
    }
}