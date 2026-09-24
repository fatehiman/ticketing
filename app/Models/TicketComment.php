<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The customer's rating of a closed ticket: 1-5 stars and an optional text. One per ticket. */
class TicketComment extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'rating', 'body'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
