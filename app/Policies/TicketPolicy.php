<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

/**
 * Admins are supervisors: they read every ticket but never write (no create, edit, status,
 * reply or delete). Tickets belong to the developers (tenants) and their customers.
 */
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->canAccessProject($ticket->project_id);
    }

    public function create(User $user): bool
    {
        return ! $user->isAdmin() && count($user->accessibleProjectIds()) > 0;
    }

    /** Developers can always edit. Customers only their own ticket while it is still pending review. */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin() || ! $this->view($user, $ticket)) {
            return false;
        }
        if ($user->isDeveloper()) {
            return true;
        }

        return $ticket->reporter_id === $user->id && $ticket->status === TicketStatus::PendingReview;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    /** Developers may set any status at any time. */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->isDeveloper() && $this->view($user, $ticket);
    }

    /** A customer can cancel their own ticket at any time (unless it is already closed). */
    public function cancel(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return false;
        }
        if ($user->isDeveloper()) {
            return $this->view($user, $ticket);
        }

        return $this->view($user, $ticket)
            && $ticket->reporter_id === $user->id
            && ! $ticket->status->isClosed();
    }

    /** Followups: developers at any time, customers only while the ticket is not closed. */
    public function reply(User $user, Ticket $ticket): bool
    {
        return ! $user->isAdmin() && $this->view($user, $ticket) && ($user->isDeveloper() || ! $ticket->status->isClosed());
    }

    /** "I read it" is only for the side that must answer (admins only read). */
    public function markRead(User $user, Ticket $ticket): bool
    {
        return ! $user->isAdmin() && $this->view($user, $ticket) && $ticket->isAwaiting($user);
    }

    /**
     * Rating (stars + optional text): only customers, only on closed tickets,
     * one per ticket. The customer who wrote it can change it.
     */
    public function comment(User $user, Ticket $ticket): bool
    {
        if (! $user->isCustomer() || ! $this->view($user, $ticket) || ! $ticket->status->isClosed()) {
            return false;
        }
        $comment = $ticket->comment;

        return $comment === null || $comment->user_id === $user->id;
    }
}
