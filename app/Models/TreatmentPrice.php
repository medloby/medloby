<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentPrice extends Model
{
    protected $fillable = [
        'business_id',
        'treatment_id',
        'branch_id',
        'doctor_id',
        'price_type',
        'price',
        'min_price',
        'max_price',
        'currency',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where(fn (Builder $prices) => $prices->whereNull('treatment_prices.branch_id')
                ->orWhereHas('branch', fn (Builder $branches) => $branches
                    ->whereColumn('branches.business_id', 'treatment_prices.business_id')))
            ->whereHas('business.memberships', fn (Builder $memberships) => $memberships
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->where(fn (Builder $scope) => $scope->where('role', 'business_owner')
                    ->orWhereNull('treatment_prices.branch_id')
                    ->orWhereHas('branches', fn (Builder $branches) => $branches
                        ->whereColumn('branches.id', 'treatment_prices.branch_id')
                        ->whereColumn('branches.business_id', 'treatment_prices.business_id')
                        ->where('branches.status', 'active')
                        ->where('business_user_branch.is_active', true))));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
