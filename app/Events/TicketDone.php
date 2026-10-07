<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the status of one ticket (or of many, in a bulk action) changed to Done.
 * Listener: App\Listeners\SendTicketSms (one SMS per customer with all ticket numbers).
 */
class TicketDone
{
    use Dispatchable;

    /** @param  array<int, Ticket>  $tickets */
    public function __construct(public array $tickets) {}
}
