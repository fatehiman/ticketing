<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One reply in the conversation of a ticket (from staff or a customer). */
class TicketFollowup extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'body', 'awaits_reply'];

    protected function casts(): array
    {
        return ['awaits_reply' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'followup_id');
    }
}
