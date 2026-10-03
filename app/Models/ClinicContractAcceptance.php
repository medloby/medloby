<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicContractAcceptance extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'platform_contract_id',
        'contract_version',
        'accepted_at',
        'ip_address',
        'user_agent',
        'acceptance_method',
        'is_accepted',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'is_accepted' => 'boolean',
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

    public function platformContract(): BelongsTo
    {
        return $this->belongsTo(
            PlatformContract::class
        );
    }
}