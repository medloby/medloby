<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchInventoryItem extends Model
{
    protected $fillable = ['branch_id', 'code', 'name', 'unit', 'minimum_stock'];

    protected $attributes = ['on_hand' => 0, 'revision' => 1];

    protected function casts(): array
    {
        return ['on_hand' => 'integer', 'minimum_stock' => 'integer', 'revision' => 'integer'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BranchInventoryMovement::class);
    }

    #[Scope]
    protected function lowStock(Builder $query): Builder
    {
        return $query->whereColumn('on_hand', '<=', 'minimum_stock');
    }
}
