<?php

namespace App\Listeners;

use App\Events\FollowupPosted;
use App\Events\TicketCreated;
use App\Events\TicketDone;
use App\Models\Ticket;
use App\Sms\Sms;
use Illuminate\Support\Collection;

/**
 * SMS about ticket conversations (only put in the outbox here; the queue worker sends them):
 * - a customer creates a ticket or writes a followup → every developer of the project
 *   (template ticket_to_developer: project name, ticket number);
 * - a developer writes a followup that waits for the customer's reply → the customer
 *   (template reply_to_customer: ticket number);
 * - a ticket's status changes to Done → the customer (template ticket_done: ticket numbers, comma separated;
 *   a bulk action sends one SMS per customer for all its tickets).
 */
class SendTicketSms
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        if ($event->user->isCustomer()) {
            $this->toDevelopers($event->ticket);
        }
    }

    public function handleFollowupPosted(FollowupPosted $event): void
    {
        $followup = $event->followup;
        $ticket = $followup->ticket;
        $author = $followup->user;

        if ($author?->isCustomer()) {
            $this->toDevelopers($ticket);
        } elseif ($author?->isDeveloper() && $followup->awaits_reply) {
            foreach ($this->customersOf($ticket) as $customer) {
                Sms::send($customer->mobile, 'reply_to_customer', [$ticket->number]);
            }
        }
    }

    /** One SMS per customer, with every Done ticket number of that customer separated by commas. */
    public function handleTicketDone(TicketDone $event): void
    {
        $numbers = [];
        $customers = [];
        foreach ($event->tickets as $ticket) {
            foreach ($this->customersOf($ticket) as $customer) {
                $customers[$customer->id] = $customer;
                $numbers[$customer->id][] = $ticket->number;
            }
        }
        foreach ($customers as $id => $customer) {
            Sms::send($customer->mobile, 'ticket_done', [implode(',', $numbers[$id])]);
        }
    }

    private function toDevelopers(Ticket $ticket): void
    {
        $project = $ticket->project;
        $developers = $project->developers()->where('is_active', true)->whereNotNull('mobile')->get();
        foreach ($developers as $developer) {
            Sms::send($developer->mobile, 'ticket_to_developer', [$project->name, $ticket->number]);
        }
    }

    /** The customer who reported the ticket; for a ticket made by staff, every customer of the project. */
    private function customersOf(Ticket $ticket): Collection
    {
        $reporter = $ticket->reporter;
        if ($reporter?->isCustomer()) {
            return $reporter->is_active && ! $reporter->trashed() ? collect([$reporter]) : collect();
        }

        return $ticket->project->customers()->where('is_active', true)->whereNotNull('mobile')->get();
    }
}
