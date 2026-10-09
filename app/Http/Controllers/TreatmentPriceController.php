<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\TreatmentPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        if (! $request->user()) {
            abort(401);
        }

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

        if (
            ! $request->user()
                ->businessMemberships()
                ->where('business_id', $validated['business_id'])
                ->where('is_active', true)
                ->exists()
        ) {
            abort(403, 'Bu işletmeye erişim yetkiniz yok.');
        }

        $branch = null;

        if (! empty($validated['branch_id'])) {
            $branch = Branch::findOrFail(
                $validated['branch_id']
            );

            if (
                (int) $branch->business_id !==
                (int) $validated['business_id']
            ) {
                abort(
                    422,
                    'Branch does not belong to the selected business.'
                );
            }
        }

        $treatment = Treatment::findOrFail(
            $validated['treatment_id']
        );

        if (! $treatment->is_active) {
            abort(
                422,
                'Treatment is not active.'
            );
        }

        if ($branch) {
            if (
                ! $treatment->branches()
                    ->where('branches.id', $branch->id)
                    ->wherePivot('is_active', true)
                    ->exists()
            ) {
                abort(
                    422,
                    'Treatment is not active for the selected branch.'
                );
            }
        }

        if (! empty($validated['doctor_id'])) {
            $doctor = Doctor::findOrFail(
                $validated['doctor_id']
            );

            if (
                ! $doctor->branches()
                    ->where(
                        'branches.business_id',
                        $validated['business_id']
                    )
                    ->wherePivot('status', 'active')
                    ->exists()
            ) {
                abort(
                    422,
                    'Doctor does not belong to the selected business.'
                );
            }

            if (
                $branch &&
                ! $doctor->branches()
                    ->where('branches.id', $branch->id)
                    ->wherePivot('status', 'active')
                    ->exists()
            ) {
                abort(
                    422,
                    'Doctor is not assigned to the selected branch.'
                );
            }
        }

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
        Request $request,
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        if (! $request->user()) {
            abort(401);
        }

        if (
            ! $request->user()
                ->businessMemberships()
                ->where(
                    'business_id',
                    $treatmentPrice->business_id
                )
                ->where('is_active', true)
                ->exists()
        ) {
            abort(403, 'Bu işletmeye erişim yetkiniz yok.');
        }

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
        if (! $request->user()) {
            abort(401);
        }

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

        $businessId = $validated['business_id']
            ?? $treatmentPrice->business_id;

        if (
            ! $request->user()
                ->businessMemberships()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->exists()
        ) {
            abort(403, 'Bu işletmeye erişim yetkiniz yok.');
        }

        $branchId = array_key_exists(
            'branch_id',
            $validated
        )
            ? $validated['branch_id']
            : $treatmentPrice->branch_id;

        $treatmentId = $validated['treatment_id']
            ?? $treatmentPrice->treatment_id;

        $doctorId = array_key_exists(
            'doctor_id',
            $validated
        )
            ? $validated['doctor_id']
            : $treatmentPrice->doctor_id;

        $branch = null;

        if ($branchId !== null) {
            $branch = Branch::findOrFail($branchId);

            if (
                (int) $branch->business_id !==
                (int) $businessId
            ) {
                abort(
                    422,
                    'Branch does not belong to the selected business.'
                );
            }
        }

        $treatment = Treatment::findOrFail($treatmentId);

        if (! $treatment->is_active) {
            abort(
                422,
                'Treatment is not active.'
            );
        }

        if ($branch) {
            if (
                ! $treatment->branches()
                    ->where('branches.id', $branch->id)
                    ->wherePivot('is_active', true)
                    ->exists()
            ) {
                abort(
                    422,
                    'Treatment is not active for the selected branch.'
                );
            }
        }

        if ($doctorId !== null) {
            $doctor = Doctor::findOrFail($doctorId);

            if (
                ! $doctor->branches()
                    ->where(
                        'branches.business_id',
                        $businessId
                    )
                    ->wherePivot('status', 'active')
                    ->exists()
            ) {
                abort(
                    422,
                    'Doctor does not belong to the selected business.'
                );
            }

            if (
                $branch &&
                ! $doctor->branches()
                    ->where('branches.id', $branch->id)
                    ->wherePivot('status', 'active')
                    ->exists()
            ) {
                abort(
                    422,
                    'Doctor is not assigned to the selected branch.'
                );
            }
        }

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
        Request $request,
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        if (! $request->user()) {
            abort(401);
        }

        if (
            ! $request->user()
                ->businessMemberships()
                ->where(
                    'business_id',
                    $treatmentPrice->business_id
                )
                ->where('is_active', true)
                ->exists()
        ) {
            abort(403, 'Bu işletmeye erişim yetkiniz yok.');
        }

        $treatmentPrice->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $treatmentPrice->fresh(),
        ]);
    }
}