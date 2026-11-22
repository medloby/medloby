<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Treatment;
use App\Models\TreatmentPrice;
use App\Rules\PriceAmount;
use App\Services\CatalogManagementAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TreatmentPriceController extends Controller
{
    public function __construct(private CatalogManagementAccess $catalogAccess) {}

    public function index(Request $request): JsonResponse
    {
        $query = TreatmentPrice::query()
            ->visibleTo($request->user())
            ->with([
                'business',
                'treatment',
                'branch',
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
        ])->header('Cache-Control', 'private, no-store');
    }

    public function workspace(Request $request, Branch $branch): JsonResponse
    {
        $filters = $request->validate(['scope' => ['sometimes', 'in:branch,clinic']]);
        $branchId = ($filters['scope'] ?? 'branch') === 'clinic' ? null : $branch->id;
        $canManage = $this->catalogAccess->canManage($request->user(), $branch->business_id, $branchId);
        $prices = TreatmentPrice::query()->visibleTo($request->user())
            ->where('business_id', $branch->business_id)->where('branch_id', $branchId)
            ->with('treatment:id,name')
            ->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'prices' => $prices,
                'can_manage' => $canManage,
                'treatments' => $canManage ? Treatment::query()->where('is_active', true)
                    ->when($branchId !== null, fn (Builder $treatments) => $treatments->whereHas('branches', fn (Builder $branches) => $branches
                        ->whereKey($branchId)->where('branch_treatment.is_active', true)))
                    ->orderBy('name')->get(['id', 'name']) : [],
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
                'prohibited',
                'integer',
            ],
            'price_type' => [
                'required',
                'string',
                'max:50',
            ],
            'price' => [
                'nullable',
                ...PriceAmount::RULES,
            ],
            'min_price' => [
                'required_if:price_type,range',
                'nullable',
                ...PriceAmount::RULES,
            ],
            'max_price' => [
                'required_if:price_type,range',
                'nullable',
                ...PriceAmount::RULES,
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

        $this->catalogAccess->authorizeManagement(
            $request->user(),
            $validated['business_id'],
            $validated['branch_id'] ?? null,
        );

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
            ]),
        ], 201);
    }

    public function show(
        Request $request,
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        $price = TreatmentPrice::query()->visibleTo($request->user())
            ->with(['business', 'treatment', 'branch'])
            ->find($treatmentPrice->id);

        abort_unless($price, 403, 'Bu tedavi fiyatına erişim yetkiniz yok.');

        return response()->json([
            'success' => true,
            'data' => $price,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(
        Request $request,
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        return DB::transaction(fn () => $this->updateAuthorizedPrice(
            $request,
            $this->lockPriceForManagement($request, $treatmentPrice),
        ), attempts: 3);
    }

    private function updateAuthorizedPrice(Request $request, TreatmentPrice $treatmentPrice): JsonResponse
    {
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
                'prohibited',
                'integer',
            ],
            'price_type' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'price' => [
                'nullable',
                ...PriceAmount::RULES,
            ],
            'min_price' => [
                'nullable',
                ...PriceAmount::RULES,
            ],
            'max_price' => [
                'nullable',
                ...PriceAmount::RULES,
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
            ],
        ]);

        $changesMinimum = array_key_exists('min_price', $validated);
        $changesMaximum = array_key_exists('max_price', $validated);
        if ($changesMinimum || $changesMaximum || ($validated['price_type'] ?? null) === 'range') {
            $range = $validated + $treatmentPrice->only(['min_price', 'max_price']);
            if (($validated['price_type'] ?? $treatmentPrice->price_type) === 'range' && ! isset($range['min_price'], $range['max_price'])) {
                throw ValidationException::withMessages([
                    isset($range['min_price']) ? 'max_price' : 'min_price' => 'Fiyat aralığının alt ve üst sınırlarını girin.',
                ]);
            }
            if (isset($range['min_price'], $range['max_price']) && $range['min_price'] > $range['max_price']) {
                throw ValidationException::withMessages([
                    ($changesMaximum ? 'max_price' : 'min_price') => 'En düşük fiyat, en yüksek fiyattan büyük olamaz.',
                ]);
            }
        }

        $businessId = (int) ($validated['business_id'] ?? $treatmentPrice->business_id);

        $branchId = array_key_exists(
            'branch_id',
            $validated
        )
            ? $validated['branch_id']
            : $treatmentPrice->branch_id;
        $branchId = $branchId === null ? null : (int) $branchId;

        if ($businessId !== $treatmentPrice->business_id || $branchId !== $treatmentPrice->branch_id) {
            $this->catalogAccess->authorizeManagement($request->user(), $businessId, $branchId);
        }

        $treatmentId = $validated['treatment_id']
            ?? $treatmentPrice->treatment_id;

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

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper(
                $validated['currency']
            );
        }

        $treatmentPrice->fill($validated);
        $changesStart = array_key_exists('valid_from', $validated);
        $changesEnd = array_key_exists('valid_until', $validated);
        if (($changesStart || $changesEnd) && $treatmentPrice->valid_from !== null && $treatmentPrice->valid_until !== null
            && $treatmentPrice->valid_from->gt($treatmentPrice->valid_until)) {
            throw ValidationException::withMessages([
                ($changesEnd ? 'valid_until' : 'valid_from') => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
            ]);
        }
        $treatmentPrice->save();

        return response()->json([
            'success' => true,
            'data' => $treatmentPrice->fresh([
                'business',
                'treatment',
                'branch',
            ]),
        ]);
    }

    public function deactivate(
        Request $request,
        TreatmentPrice $treatmentPrice
    ): JsonResponse {
        $price = DB::transaction(function () use ($request, $treatmentPrice): TreatmentPrice {
            $price = $this->lockPriceForManagement($request, $treatmentPrice);
            $price->update(['is_active' => false]);

            return $price;
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'data' => $price,
        ]);
    }

    private function lockPriceForManagement(Request $request, TreatmentPrice $price): TreatmentPrice
    {
        $price = TreatmentPrice::query()->clinicPrices()->lockForUpdate()->findOrFail($price->getKey());
        $this->catalogAccess->authorizeManagement($request->user(), $price->business_id, $price->branch_id);

        return $price;
    }
}
