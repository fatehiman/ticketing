<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a new ticket is saved (web form, bot API or a manual bill item). */
class TicketCreated
{
    use Dispatchable;

    public function __construct(public Ticket $ticket, public User $user) {}
}
