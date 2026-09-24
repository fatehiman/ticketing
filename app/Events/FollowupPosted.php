<?php

namespace App\Events;

use App\Models\TicketFollowup;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a followup is saved. When the followup waits for an answer,
 * $followup->ticket->awaiting_reply says which side must answer.
 * Hook for notifications (for example SMS to the customer) — no listener yet.
 */
class FollowupPosted
{
    use Dispatchable;

    public function __construct(public TicketFollowup $followup) {}
}
