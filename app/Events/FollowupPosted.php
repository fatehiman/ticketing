<?php

namespace App\Events;

use App\Models\TicketFollowup;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a followup is saved. When the followup waits for an answer,
 * $followup->ticket->awaiting_reply says which side must answer.
 * Listener: App\Listeners\SendTicketSms (SMS to developers or to the customer).
 */
class FollowupPosted
{
    use Dispatchable;

    public function __construct(public TicketFollowup $followup) {}
}
