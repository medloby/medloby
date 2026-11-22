<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\PackageService;
use App\Models\TreatmentPackage;
use App\Services\CatalogManagementAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackageServiceController extends Controller
{
    public function __construct(private CatalogManagementAccess $catalogAccess) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->publicServices()
            ->orderBy('sort_order')
            ->orderBy('name');

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

    public function manage(Request $request, ?Business $business = null): JsonResponse
    {
        $businessId = $business?->id;
        $this->catalogAccess->authorizeManagement($request->user(), $businessId);

        $services = PackageService::query()->where('business_id', $businessId)
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get(['id', 'business_id', 'name', 'service_type', 'description', 'unit', 'is_active', 'sort_order']);

        return response()->json([
            'success' => true,
            'data' => ['services' => $services],
        ])->header('Cache-Control', 'private, no-store');
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

        $this->catalogAccess->authorizeManagement($request->user(), $validated['business_id'] ?? null);

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
        $service = $this->publicServices()->findOrFail($packageService->id);
        $packages = $service->packages()
            ->publishedInCatalog()
            ->when($service->business_id !== null, fn ($query) => $query
                ->where('treatment_packages.business_id', $service->business_id))
            ->orderBy('treatment_packages.name')
            ->get(['treatment_packages.id', 'treatment_packages.name', 'treatment_packages.slug', 'treatment_packages.package_type'])
            ->map(fn (TreatmentPackage $package) => $package->only(['id', 'name', 'slug', 'package_type']));

        return response()->json([
            'success' => true,
            'data' => [...$service->toArray(), 'packages' => $packages],
        ]);
    }

    private function publicServices(): Builder
    {
        return PackageService::query()
            ->publishedInCatalog()
            ->select(['id', 'business_id', 'name', 'slug', 'service_type', 'description', 'unit', 'is_active', 'sort_order'])
            ->with('business:id,name');
    }

    public function update(
        Request $request,
        PackageService $packageService
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $packageService->business_id);

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

        if (array_key_exists('business_id', $validated)) {
            $this->catalogAccess->authorizeManagement($request->user(), $validated['business_id'] ?? null);
        }

        if (isset($validated['business_id'])
            && (int) $validated['business_id'] !== (int) $packageService->business_id
            && $packageService->packages()->where('treatment_packages.business_id', '!=', $validated['business_id'])->exists()) {
            throw ValidationException::withMessages([
                'business_id' => 'Hizmet, başka bir işletmeye ait pakette kullanılıyor.',
            ]);
        }

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
            'data' => $packageService->fresh('business'),
        ]);
    }

    public function deactivate(
        Request $request,
        PackageService $packageService
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $packageService->business_id);

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
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
