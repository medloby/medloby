<?php

namespace App\Http\Controllers;

use App\Models\PackageServiceItem;
use App\Models\TreatmentPackage;
use App\Services\CatalogManagementAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageServiceItemController extends Controller
{
    public function __construct(private CatalogManagementAccess $catalogAccess) {}

    public function index(
        TreatmentPackage $treatmentPackage
    ): JsonResponse {
        $items = $this->publicItems($treatmentPackage)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(
        Request $request,
        TreatmentPackage $treatmentPackage
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $treatmentPackage->business_id);

        $validated = $request->validate([
            'package_service_id' => [
                'required',
                'integer',
                Rule::exists('package_services', 'id')->where(
                    fn ($query) => $query->whereNull('business_id')
                        ->orWhere('business_id', $treatmentPackage->business_id)
                ),
            ],
            'quantity' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        $alreadyExists = PackageServiceItem::query()
            ->where(
                'treatment_package_id',
                $treatmentPackage->id
            )
            ->where(
                'package_service_id',
                $validated['package_service_id']
            )
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Bu hizmet pakete zaten eklenmiş.',
            ], 422);
        }

        $item = PackageServiceItem::create([
            'treatment_package_id' => $treatmentPackage->id,
            'package_service_id' => $validated['package_service_id'],
            'quantity' => $validated['quantity'] ?? 1,
            'notes' => $validated['notes'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $item->fresh([
                'packageService',
            ]),
        ], 201);
    }

    public function show(
        TreatmentPackage $treatmentPackage,
        PackageServiceItem $packageServiceItem
    ): JsonResponse {
        $items = $this->publicItems($treatmentPackage);

        if (
            $packageServiceItem->treatment_package_id
            !== $treatmentPackage->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu öğe belirtilen pakete ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $items->findOrFail($packageServiceItem->id),
        ]);
    }

    private function publicItems(TreatmentPackage $package): Builder
    {
        abort_unless($package->isPublishedInCatalog(), 404);

        return PackageServiceItem::query()
            ->where('treatment_package_id', $package->id)
            ->whereHas('packageService', fn (Builder $services) => $services
                ->publishedInCatalog()
                ->where(fn (Builder $scope) => $scope->whereNull('business_id')->orWhere('business_id', $package->business_id)))
            ->select(['id', 'treatment_package_id', 'package_service_id', 'quantity', 'sort_order'])
            ->with('packageService:id,business_id,name,service_type');
    }

    public function update(
        Request $request,
        TreatmentPackage $treatmentPackage,
        PackageServiceItem $packageServiceItem
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $treatmentPackage->business_id);

        if (
            $packageServiceItem->treatment_package_id
            !== $treatmentPackage->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu öğe belirtilen pakete ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'package_service_id' => [
                'sometimes',
                'integer',
                Rule::exists('package_services', 'id')->where(
                    fn ($query) => $query->whereNull('business_id')
                        ->orWhere('business_id', $treatmentPackage->business_id)
                ),
            ],
            'quantity' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        if (isset($validated['package_service_id'])) {
            $duplicate = PackageServiceItem::query()
                ->where(
                    'treatment_package_id',
                    $treatmentPackage->id
                )
                ->where(
                    'package_service_id',
                    $validated['package_service_id']
                )
                ->where(
                    'id',
                    '!=',
                    $packageServiceItem->id
                )
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu hizmet pakete zaten eklenmiş.',
                ], 422);
            }
        }

        $packageServiceItem->update($validated);

        return response()->json([
            'success' => true,
            'data' => $packageServiceItem->fresh([
                'packageService',
            ]),
        ]);
    }

    public function destroy(
        Request $request,
        TreatmentPackage $treatmentPackage,
        PackageServiceItem $packageServiceItem
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $treatmentPackage->business_id);

        if (
            $packageServiceItem->treatment_package_id
            !== $treatmentPackage->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu öğe belirtilen pakete ait değil.',
            ], 404);
        }

        $packageServiceItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hizmet paketten çıkarıldı.',
        ]);
    }
}
