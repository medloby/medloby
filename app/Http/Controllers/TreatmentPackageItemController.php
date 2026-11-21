<?php

namespace App\Http\Controllers;

use App\Models\TreatmentPackage;
use App\Models\TreatmentPackageItem;
use App\Services\PackageCatalogAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreatmentPackageItemController extends Controller
{
    public function __construct(private PackageCatalogAccess $catalogAccess) {}

    public function index(
        TreatmentPackage $treatmentPackage
    ): JsonResponse {
        abort_unless($treatmentPackage->is_active, 404);

        $items = TreatmentPackageItem::query()
            ->where(
                'treatment_package_id',
                $treatmentPackage->id
            )
            ->whereHas('treatment', fn ($query) => $query->where('is_active', true))
            ->with('treatment')
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
                'exists:treatments,id',
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
        abort_unless($treatmentPackage->is_active, 404);

        if (
            $treatmentPackageItem->treatment_package_id
            !== $treatmentPackage->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu öğe belirtilen pakete ait değil.',
            ], 404);
        }

        abort_unless($treatmentPackageItem->treatment?->is_active, 404);

        return response()->json([
            'success' => true,
            'data' => $treatmentPackageItem->load([
                'treatment',
            ]),
        ]);
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
                'exists:treatments,id',
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
}
