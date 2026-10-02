<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessUserPermission extends Model
{
    protected $table = 'business_user_permission';

    protected $fillable = [
        'business_id',
        'user_id',
        'permission_id',
        'granted_by_user_id',
        'is_allowed',
    ];

    protected function casts(): array
    {
        return [
            'is_allowed' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'granted_by_user_id'
        );
    }
}