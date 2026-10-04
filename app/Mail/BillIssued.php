<?php

namespace App\Mail;

use App\Models\Bill;
use App\Support\Bills;
use App\Support\Dates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "You have a new bill" mail to the customer. Sent by the queue worker (retries after 1, 5, 10, 30 minutes). */
class BillIssued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 600, 1800];

    public function __construct(public Bill $bill)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.bill.subject', ['number' => $this->bill->number, 'project' => $this->bill->project->name]));
    }

    public function content(): Content
    {
        $bill = $this->bill->loadMissing(['items', 'project', 'customer', 'creator']);
        $calendar = $bill->customer->calendar;

        return new Content(view: 'mail.bill-issued', with: [
            'bill' => $bill,
            'currency' => __('app.currency.'.($bill->project->currency ?: 'IRT')),
            'issuedOn' => Dates::formatIn($bill->issued_on, $calendar),
            'dueOn' => Dates::formatIn($bill->due_on, $calendar),
            'debt' => Bills::debtOf($bill->customer),
            'url' => route('bills.show', $bill),
        ]);
    }
}
