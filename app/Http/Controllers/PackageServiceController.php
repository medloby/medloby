<?php

namespace App\Http\Controllers;

use App\Models\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PackageService::query()
            ->with('business')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('business_id')) {
            $query->where(
                'business_id',
                $request->integer('business_id')
            );
        }

        if ($request->filled('service_type')) {
            $query->where(
                'service_type',
                $request->string('service_type')->value()
            );
        }

        $services = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'services' => $services,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'nullable',
                'integer',
                'exists:businesses,id',
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
            ],
            'service_type' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'unit' => [
                'nullable',
                'string',
                'max:100',
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

        $businessId = $validated['business_id'] ?? null;

        $validated['slug'] = $this->resolveSlug(
            $validated['name'],
            $validated['slug'] ?? null,
            $businessId
        );

        $validated['is_active'] =
            $validated['is_active'] ?? true;

        $validated['sort_order'] =
            $validated['sort_order'] ?? 0;

        $service = PackageService::create($validated);

        return response()->json([
            'success' => true,
            'data' => $service->fresh([
                'business',
            ]),
        ], 201);
    }

    public function show(
        PackageService $packageService
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $packageService->load([
                'business',
                'packages',
            ]),
        ]);
    }

    public function update(
        Request $request,
        PackageService $packageService
    ): JsonResponse {
        $validated = $request->validate([
            'business_id' => [
                'nullable',
                'integer',
                'exists:businesses,id',
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'service_type' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'unit' => [
                'nullable',
                'string',
                'max:100',
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

        if (
            array_key_exists('name', $validated)
            || array_key_exists('slug', $validated)
            || array_key_exists('business_id', $validated)
        ) {
            $validated['slug'] = $this->resolveSlug(
                $validated['name']
                    ?? $packageService->name,
                $validated['slug']
                    ?? $packageService->slug,
                array_key_exists(
                    'business_id',
                    $validated
                )
                    ? $validated['business_id']
                    : $packageService->business_id,
                $packageService->id
            );
        }

        $packageService->update($validated);

        return response()->json([
            'success' => true,
            'data' => $packageService->fresh([
                'business',
                'packages',
            ]),
        ]);
    }

    public function deactivate(
        PackageService $packageService
    ): JsonResponse {
        $packageService->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $packageService->fresh(),
        ]);
    }

    private function resolveSlug(
        string $name,
        ?string $requestedSlug,
        ?int $businessId,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug(
            $requestedSlug ?: $name
        );

        if ($baseSlug === '') {
            $baseSlug = 'service';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            PackageService::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when(
                    $ignoreId !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $ignoreId
                    )
                )
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}