<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BusinessUserBranch extends Pivot
{
    protected function casts(): array
    {
        return ['permission_names' => 'array'];
    }
}
