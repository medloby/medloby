<?php

namespace App\Models;

use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    protected $fillable = ['subject'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    #[Scope]
    protected function awaitingSupportReply(Builder $tickets): Builder
    {
        return $tickets->whereNull('support_tickets.closed_at')->where($this->lastMessageDirection(), '=', false);
    }

    #[Scope]
    protected function withLastMessageDirection(Builder $tickets): Builder
    {
        return $tickets->addSelect(['last_message_from_support' => $this->lastMessageDirection()])
            ->withCasts(['last_message_from_support' => 'boolean']);
    }

    /** @return Builder<SupportTicketMessage> */
    private function lastMessageDirection(): Builder
    {
        return SupportTicketMessage::query()->select('from_support')
            ->whereColumn('support_ticket_id', 'support_tickets.id')->latest('id')->limit(1);
    }
}
