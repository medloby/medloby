<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['assigned_to_user_id', 'assigned_by_user_id', 'action'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
