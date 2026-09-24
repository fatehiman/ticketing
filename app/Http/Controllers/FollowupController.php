<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;

/** Replies in the ticket conversation, and "I read it". */
class FollowupController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function store(Request $request, Ticket $ticket)
    {
        $this->authorize('reply', $ticket);
        $request->validate([
            'body' => ['nullable', 'string', 'max:2000000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'extensions:'.implode(',', TicketController::FILE_TYPES)],
        ], [], [
            'body' => __('tickets.followup.body'),
            'attachments.*' => __('tickets.fields.attachments'),
        ]);

        $followup = $this->tickets->addFollowup(
            $ticket,
            $request->user(),
            $request->input('body'),
            $request->boolean('awaits_reply'),
            $request->file('attachments', []),
        );

        return redirect()->to(route('tickets.show', $ticket).'#followup-'.$followup->id)->with('success', __('tickets.followup.sent'));
    }

    public function read(Request $request, Ticket $ticket)
    {
        $this->authorize('markRead', $ticket);
        $this->tickets->markRead($ticket, $request->user());

        return back()->with('success', __('tickets.followup.marked_read'));
    }
}
