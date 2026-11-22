<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicTreatmentPackageResource;
use App\Models\Branch;
use App\Models\Business;
use App\Models\PackageService;
use App\Models\Treatment;
use App\Models\TreatmentPackage;
use App\Services\CatalogManagementAccess;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TreatmentPackageController extends Controller
{
    public function __construct(private CatalogManagementAccess $catalogAccess) {}

    public function index(Request $request): JsonResponse
    {
        $query = TreatmentPackage::query()
            ->publishedInCatalog()
            ->withPublicContents()
            ->orderBy('name')
            ->orderBy('id');

        if ($request->boolean('offer_enabled')) {
            $query->where('is_offer_enabled', true);
        }

        if ($request->filled('business_id')) {
            $query->where(
                'business_id',
                $request->integer('business_id')
            );
        }

        if ($request->filled('branch_id')) {
            $query->where(
                'branch_id',
                $request->integer('branch_id')
            );
        }

        if ($request->filled('package_type')) {
            $query->where(
                'package_type',
                $request->string('package_type')->value()
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'packages' => PublicTreatmentPackageResource::collection($query->get()),
            ],
        ]);
    }

    public function discover(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);
        $search = $filters['q'] ?? '';
        $city = $filters['city'] ?? '';

        $packages = TreatmentPackage::query()
            ->availableForEnquiries()
            ->withPublicContents()
            ->when($search !== '', function (Builder $packages) use ($search): void {
                $term = '%'.$search.'%';
                $packages->where(fn (Builder $matching) => $matching
                    ->whereLike('treatment_packages.name', $term)
                    ->orWhereHas('business', fn (Builder $business) => $business->whereLike('businesses.name', $term))
                    ->orWhereHas('branch', fn (Builder $branch) => $branch->whereLike('branches.name', $term))
                    ->orWhereHas('treatments', fn (Builder $treatment) => $treatment->whereLike('treatments.name', $term)));
            })
            ->when($city !== '', function (Builder $packages) use ($city): void {
                $term = '%'.$city.'%';
                $packages->where(fn (Builder $matching) => $matching
                    ->whereHas('branch', fn (Builder $branch) => $branch->whereLike('branches.city', $term))
                    ->orWhere(fn (Builder $withoutBranch) => $withoutBranch->whereNull('treatment_packages.branch_id')
                        ->whereHas('business', fn (Builder $business) => $business->whereLike('businesses.city', $term))));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12)
            ->appends($filters)
            ->through(fn (TreatmentPackage $package) => new PublicTreatmentPackageResource($package));

        return response()->json(['success' => true, 'data' => $packages]);
    }

    public function manage(Request $request, Business $business): JsonResponse
    {
        $this->catalogAccess->authorizeManagement($request->user(), $business->id);

        $packages = $business->treatmentPackages()->with([
            'treatments' => fn ($relation) => $relation->withPivot('id')->orderByPivot('sort_order'),
            'services' => fn ($relation) => $relation->withPivot('id')->orderByPivot('sort_order'),
        ])->orderBy('name')->get();

        $treatments = Treatment::query()
            ->where('is_active', true)
            ->with(['branches' => fn ($relation) => $relation
                ->where('branches.business_id', $business->id)
                ->where('branch_treatment.is_active', true)
                ->select('branches.id')])
            ->orderBy('name')
            ->get(['id', 'name']);

        $services = PackageService::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('business_id', $business->id)->orWhereNull('business_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'branches' => $business->branches()->orderBy('name')->get(['id', 'name']),
                'packages' => $packages,
                'treatments' => $treatments,
                'services' => $services,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
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
            'description' => [
                'nullable',
                'string',
            ],
            'package_type' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],
            'included_services' => [
                'nullable',
                'string',
            ],
            'excluded_services' => [
                'nullable',
                'string',
            ],
            'duration_days' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'includes_hotel' => [
                'sometimes',
                'boolean',
            ],
            'includes_transfer' => [
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
            'valid_from' => [
                'nullable',
                'date',
            ],
            'valid_until' => [
                'nullable',
                'date',
            ],
        ]);

        $this->catalogAccess->authorizeManagement($request->user(), (int) $validated['business_id']);

        $this->validateValidityDates($validated['valid_from'] ?? null, $validated['valid_until'] ?? null);

        $this->validateBranch(
            (int) $validated['business_id'],
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null
        );

        $validated['slug'] = $this->resolveSlug(
            $validated['name'],
            $validated['slug'] ?? null,
            (int) $validated['business_id']
        );

        $validated['currency'] = strtoupper(
            $validated['currency'] ?? 'TRY'
        );

        $validated['package_type'] =
            $validated['package_type'] ?? 'treatment';

        $validated['includes_hotel'] =
            $validated['includes_hotel'] ?? false;

        $validated['includes_transfer'] =
            $validated['includes_transfer'] ?? false;

        $validated['is_offer_enabled'] =
            $validated['is_offer_enabled'] ?? true;

        $validated['is_active'] =
            $validated['is_active'] ?? true;

        $package = TreatmentPackage::create(
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $package->fresh([
                'business',
                'branch',
                'treatments',
                'services',
            ]),
        ], 201);
    }

    public function show(
        TreatmentPackage $treatmentPackage
    ): JsonResponse {
        $package = TreatmentPackage::query()->publishedInCatalog()->withPublicContents()
            ->findOrFail($treatmentPackage->getKey());

        return response()->json([
            'success' => true,
            'data' => new PublicTreatmentPackageResource($package),
        ]);
    }

    public function update(
        Request $request,
        TreatmentPackage $treatmentPackage
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $treatmentPackage->business_id);

        $validated = $request->validate([
            'business_id' => [
                'sometimes',
                'integer',
                'exists:businesses,id',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
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
            'description' => [
                'nullable',
                'string',
            ],
            'package_type' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],
            'included_services' => [
                'nullable',
                'string',
            ],
            'excluded_services' => [
                'nullable',
                'string',
            ],
            'duration_days' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'includes_hotel' => [
                'sometimes',
                'boolean',
            ],
            'includes_transfer' => [
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
            'valid_from' => [
                'nullable',
                'date',
            ],
            'valid_until' => [
                'nullable',
                'date',
            ],
        ]);

        if (array_key_exists('business_id', $validated)) {
            $this->catalogAccess->authorizeManagement($request->user(), (int) $validated['business_id']);
        }

        $businessId = (int) ($validated['business_id'] ?? $treatmentPackage->business_id);
        if (array_key_exists('business_id', $validated) || array_key_exists('branch_id', $validated)) {
            $branchId = array_key_exists('branch_id', $validated)
                ? $validated['branch_id']
                : $treatmentPackage->branch_id;

            $branchId = $branchId === null ? null : (int) $branchId;
            $this->validateBranch($businessId, $branchId);

            if ($branchId !== null && $branchId !== $treatmentPackage->branch_id
                && $treatmentPackage->treatments()->whereNotIn(
                    'treatments.id',
                    Treatment::query()->eligibleForPackage($branchId)->select('treatments.id')
                )->exists()) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Pakette seçilen şubede aktif olarak sunulmayan tedaviler var.',
                ]);
            }
        }

        if ($businessId !== (int) $treatmentPackage->business_id
            && $treatmentPackage->services()->whereNotNull('package_services.business_id')
                ->where('package_services.business_id', '!=', $businessId)->exists()) {
            throw ValidationException::withMessages([
                'business_id' => 'Paket, başka bir işletmeye ait ek hizmet içeriyor.',
            ]);
        }

        if (array_key_exists('valid_from', $validated) || array_key_exists('valid_until', $validated)) {
            $dates = $validated + [
                'valid_from' => $treatmentPackage->valid_from,
                'valid_until' => $treatmentPackage->valid_until,
            ];

            $this->validateValidityDates($dates['valid_from'], $dates['valid_until']);
        }

        if (
            array_key_exists('name', $validated)
            || array_key_exists('slug', $validated)
            || $businessId !== (int) $treatmentPackage->business_id
        ) {
            $validated['slug'] = $this->resolveSlug(
                $validated['name']
                    ?? $treatmentPackage->name,
                $validated['slug']
                    ?? $treatmentPackage->slug,
                $businessId,
                $treatmentPackage->id
            );
        }

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper(
                $validated['currency']
            );
        }

        $treatmentPackage->update(
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $treatmentPackage->fresh([
                'business',
                'branch',
                'treatments',
                'services',
            ]),
        ]);
    }

    public function deactivate(
        Request $request,
        TreatmentPackage $treatmentPackage
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $treatmentPackage->business_id);

        $treatmentPackage->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatmentPackage->fresh(),
        ]);
    }

    private function validateValidityDates(string|DateTimeInterface|null $from, string|DateTimeInterface|null $until): void
    {
        if ($from !== null && $until !== null
            && Carbon::parse($until)->toDateString() < Carbon::parse($from)->toDateString()) {
            throw ValidationException::withMessages([
                'valid_until' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
            ]);
        }
    }

    private function validateBranch(int $businessId, ?int $branchId): void
    {
        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->where('business_id', $businessId)->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Şube seçilen işletmeye ait olmalıdır.',
            ]);
        }
    }

    private function resolveSlug(
        string $name,
        ?string $requestedSlug,
        int $businessId,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug(
            $requestedSlug ?: $name
        );

        if ($baseSlug === '') {
            $baseSlug = 'package';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            TreatmentPackage::query()
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
