<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\TreatmentPackage;
use App\Services\PackageCatalogAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TreatmentPackageController extends Controller
{
    public function __construct(private PackageCatalogAccess $catalogAccess) {}

    public function index(Request $request): JsonResponse
    {
        $query = TreatmentPackage::query()
            ->where('is_active', true)
            ->with([
                'business',
                'branch',
                'treatments' => fn ($query) => $query->where('treatments.is_active', true),
                'services' => fn ($query) => $query->where('package_services.is_active', true),
            ])
            ->orderBy('name');

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

        if ($request->filled('currency')) {
            $query->where(
                'currency',
                strtoupper(
                    $request->string('currency')->value()
                )
            );
        }

        $packages = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'packages' => $packages,
            ],
        ]);
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
                'after_or_equal:valid_from',
            ],
        ]);

        $this->catalogAccess->authorizeManagement($request->user(), (int) $validated['business_id']);

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
        abort_unless($treatmentPackage->is_active, 404);

        $treatmentPackage->load([
            'business',
            'branch',
            'treatments' => fn ($query) => $query->where('treatments.is_active', true),
            'services' => fn ($query) => $query->where('package_services.is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatmentPackage,
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

            $this->validateBranch($businessId, $branchId === null ? null : (int) $branchId);
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

            if ($dates['valid_from'] !== null && $dates['valid_until'] !== null
                && Carbon::parse($dates['valid_until'])->toDateString() < Carbon::parse($dates['valid_from'])->toDateString()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
                ]);
            }
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
