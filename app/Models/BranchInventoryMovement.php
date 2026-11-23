<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchInventoryMovement extends Model
{
    protected $fillable = ['created_by_user_id', 'request_key', 'direction', 'quantity', 'balance_after', 'description'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'balance_after' => 'integer'];
    }
}
