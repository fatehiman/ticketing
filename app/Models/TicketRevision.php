<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'user_id', 'action', 'changes', 'snapshot'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'snapshot' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
