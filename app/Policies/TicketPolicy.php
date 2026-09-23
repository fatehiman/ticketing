<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->canAccessProject($ticket->project_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || count($user->accessibleProjectIds()) > 0;
    }

    /** Staff can always edit. Customers only their own ticket while it is still pending review. */
    public function update(User $user, Ticket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }
        if ($user->isStaff()) {
            return true;
        }

        return $ticket->reporter_id === $user->id && $ticket->status === TicketStatus::PendingReview;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    /** Staff may set any status at any time. */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->view($user, $ticket);
    }

    /** A customer can cancel their own ticket at any time (unless it is already closed). */
    public function cancel(User $user, Ticket $ticket): bool
    {
        if ($user->isStaff()) {
            return $this->view($user, $ticket);
        }

        return $this->view($user, $ticket)
            && $ticket->reporter_id === $user->id
            && ! $ticket->status->isClosed();
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
