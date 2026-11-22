<?php

namespace App\Models;

use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['from_support', 'sender_user_id', 'body'];

    protected function casts(): array
    {
        return ['from_support' => 'boolean'];
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }
}
