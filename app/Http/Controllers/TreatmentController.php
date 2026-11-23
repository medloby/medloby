<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TreatmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only') || ! auth('sanctum')->user()?->isVerifiedPlatformAdmin();
        $query = Treatment::query()
            ->with(['treatmentCategory' => fn ($category) => $category->when($activeOnly, fn ($query) => $query->where('is_active', true))])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($activeOnly) {
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
        if ($request->input('slug') === null && is_string($request->input('name'))) {
            $request->merge(['slug' => Str::slug($request->input('name'))]);
        }

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
                'required',
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
        ], [
            'slug.required' => 'Tedavi adı için bir adres adı girin.',
            'slug.unique' => 'Bu tedavi adres adı zaten kullanılıyor.',
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
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'preparation' => $validated['preparation'] ?? null,
            'aftercare' => $validated['aftercare'] ?? null,
            'included_services' => $validated['included_services'] ?? null,
            'excluded_services' => $validated['excluded_services'] ?? null,
            'image' => $validated['image'] ?? null,
            'is_online_bookable' => $validated['is_online_bookable'] ?? false,
            'is_offer_enabled' => $validated['is_offer_enabled'] ?? true,
            'is_active' => $validated['is_active'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatment->fresh('treatmentCategory'),
        ], 201);
    }

    public function show(Treatment $treatment): JsonResponse
    {
        $publicOnly = ! auth('sanctum')->user()?->isVerifiedPlatformAdmin();
        abort_if($publicOnly && ! $treatment->is_active, 404);
        $treatment->load(['treatmentCategory' => fn ($category) => $category->when($publicOnly, fn ($query) => $query->where('is_active', true))]);
        $packages = $treatment->packages()
            ->publishedInCatalog()
            ->orderBy('treatment_packages.name')
            ->get(['treatment_packages.id', 'treatment_packages.name', 'treatment_packages.slug', 'treatment_packages.package_type'])
            ->map(fn (TreatmentPackage $package) => $package->only(['id', 'name', 'slug', 'package_type']));

        return response()->json([
            'success' => true,
            'data' => [...$treatment->toArray(), 'packages' => $packages],
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
