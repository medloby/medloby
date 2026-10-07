<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\TreatmentPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TreatmentPriceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TreatmentPrice::query()
            ->with([
                'business',
                'treatment',
                'branch',
                'doctor',
            ])
            ->orderByDesc('id');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('business_id')) {
            $query->where(
                'business_id',
                $request->integer('business_id')
            );
        }

        if ($request->filled('treatment_id')) {
            $query->where(
                'treatment_id',
                $request->integer('treatment_id')
            );
        }

        if ($request->filled('branch_id')) {
            $query->where(
                'branch_id',
                $request->integer('branch_id')
            );
        }

        if ($request->filled('doctor_id')) {
            $query->where(
                'doctor_id',
                $request->integer('doctor_id')
            );
        }

        if ($request->filled('currency')) {
            $query->where(
                'currency',
                strtoupper($request->string('currency')->value())
            );
        }

        $prices = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'prices' => $prices,
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
            'treatment_id' => [
                'required',
                'integer',
                'exists:treatments,id',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'doctor_id' => [
                'nullable',
                'integer',
                'exists:doctors,id',
            ],
            'price_type' => [
                'required',
                'string',
                'max:50',
            ],
            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'min_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                'gte:min_price',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
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

        $validated['currency'] = strtoupper(
            $validated['currency']
        );

        $validated['is_active'] =
            $validated['is_active'] ?? true;

        $price = TreatmentPrice::create($validated);

        return response()->json([
            'success' => true,
            'data' => $price->fresh([
                'business',
                'treatment',
                'branch',
                'doctor',
            ]),
        ], 201);
    }

    public function show(
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        $treatmentPrice->load([
            'business',
            'treatment',
            'branch',
            'doctor',
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatmentPrice,
        ]);
    }

    public function update(
        Request $request,
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        $validated = $request->validate([
            'business_id' => [
                'sometimes',
                'integer',
                'exists:businesses,id',
            ],
            'treatment_id' => [
                'sometimes',
                'integer',
                'exists:treatments,id',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'doctor_id' => [
                'nullable',
                'integer',
                'exists:doctors,id',
            ],
            'price_type' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'min_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                'gte:min_price',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
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

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper(
                $validated['currency']
            );
        }

        $treatmentPrice->update($validated);

        return response()->json([
            'success' => true,
            'data' => $treatmentPrice->fresh([
                'business',
                'treatment',
                'branch',
                'doctor',
            ]),
        ]);
    }

    public function deactivate(
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        $treatmentPrice->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatmentPrice->fresh(),
        ]);
    }
}