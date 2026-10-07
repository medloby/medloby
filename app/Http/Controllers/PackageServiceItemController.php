<?php

namespace App\Http\Controllers;

use App\Models\PackageServiceItem;
use App\Models\TreatmentPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageServiceItemController extends Controller
{
    public function index(
        TreatmentPackage $treatmentPackage
    ): JsonResponse {
        $items = PackageServiceItem::query()
            ->where(
                'treatment_package_id',
                $treatmentPackage->id
            )
            ->with('packageService')
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
        $validated = $request->validate([
            'package_service_id' => [
                'required',
                'integer',
                'exists:package_services,id',
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
            'data' => $packageServiceItem->load([
                'packageService',
            ]),
        ]);
    }

    public function update(
        Request $request,
        TreatmentPackage $treatmentPackage,
        PackageServiceItem $packageServiceItem
    ): JsonResponse {
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
                'exists:package_services,id',
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
        TreatmentPackage $treatmentPackage,
        PackageServiceItem $packageServiceItem
    ): JsonResponse {
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