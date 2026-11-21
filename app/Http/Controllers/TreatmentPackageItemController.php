<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\TreatmentPackage;
use App\Models\TreatmentPackageItem;
use App\Services\CatalogManagementAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TreatmentPackageItemController extends Controller
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
            'treatment_id' => [
                'required',
                'integer',
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

        $this->validateTreatment((int) $validated['treatment_id'], $treatmentPackage);

        $alreadyExists = TreatmentPackageItem::query()
            ->where(
                'treatment_package_id',
                $treatmentPackage->id
            )
            ->where(
                'treatment_id',
                $validated['treatment_id']
            )
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Bu tedavi pakete zaten eklenmiş.',
            ], 422);
        }

        $item = TreatmentPackageItem::create([
            'treatment_package_id' => $treatmentPackage->id,
            'treatment_id' => $validated['treatment_id'],
            'quantity' => $validated['quantity'] ?? 1,
            'notes' => $validated['notes'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $item->fresh([
                'treatment',
            ]),
        ], 201);
    }

    public function show(
        TreatmentPackage $treatmentPackage,
        TreatmentPackageItem $treatmentPackageItem
    ): JsonResponse {
        $items = $this->publicItems($treatmentPackage);

        if (
            $treatmentPackageItem->treatment_package_id
            !== $treatmentPackage->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu öğe belirtilen pakete ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $items->findOrFail($treatmentPackageItem->id),
        ]);
    }

    private function publicItems(TreatmentPackage $package): Builder
    {
        abort_unless($package->isPublishedInCatalog(), 404);

        return TreatmentPackageItem::query()
            ->where('treatment_package_id', $package->id)
            ->whereHas('treatment', fn ($query) => $query->where('is_active', true))
            ->select(['id', 'treatment_package_id', 'treatment_id', 'quantity', 'sort_order'])
            ->with('treatment:id,name');
    }

    public function update(
        Request $request,
        TreatmentPackage $treatmentPackage,
        TreatmentPackageItem $treatmentPackageItem
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $treatmentPackage->business_id);

        if (
            $treatmentPackageItem->treatment_package_id
            !== $treatmentPackage->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu öğe belirtilen pakete ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'treatment_id' => [
                'sometimes',
                'integer',
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

        if (isset($validated['treatment_id'])) {
            $this->validateTreatment((int) $validated['treatment_id'], $treatmentPackage);

            $duplicate = TreatmentPackageItem::query()
                ->where(
                    'treatment_package_id',
                    $treatmentPackage->id
                )
                ->where(
                    'treatment_id',
                    $validated['treatment_id']
                )
                ->where(
                    'id',
                    '!=',
                    $treatmentPackageItem->id
                )
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu tedavi pakete zaten eklenmiş.',
                ], 422);
            }
        }

        $treatmentPackageItem->update($validated);

        return response()->json([
            'success' => true,
            'data' => $treatmentPackageItem->fresh([
                'treatment',
            ]),
        ]);
    }

    public function destroy(
        Request $request,
        TreatmentPackage $treatmentPackage,
        TreatmentPackageItem $treatmentPackageItem
    ): JsonResponse {
        $this->catalogAccess->authorizeManagement($request->user(), $treatmentPackage->business_id);

        if (
            $treatmentPackageItem->treatment_package_id
            !== $treatmentPackage->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu öğe belirtilen pakete ait değil.',
            ], 404);
        }

        $treatmentPackageItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tedavi paketten çıkarıldı.',
        ]);
    }

    private function validateTreatment(int $treatmentId, TreatmentPackage $package): void
    {
        if (! Treatment::query()->eligibleForPackage($package->branch_id)->whereKey($treatmentId)->exists()) {
            throw ValidationException::withMessages([
                'treatment_id' => 'Paket için aktif ve varsa paket şubesinde sunulan bir tedavi seçilmelidir.',
            ]);
        }
    }
}
