<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessUser extends Model
{
    protected $table = 'business_user';

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function activeForBranch(Builder $query, Branch $branch): Builder
    {
        return $query->where('business_user.business_id', $branch->business_id)
            ->where('business_user.is_active', true)
            ->where(fn (Builder $membership) => $membership->where('business_user.role', 'business_owner')
                ->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($branch->id)
                    ->where('business_user_branch.is_active', true)));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'business_user_branch',
            'business_user_id',
            'branch_id'
        )
            ->withPivot([
                'is_active',
            ])
            ->withTimestamps();
    }
}
