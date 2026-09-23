<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $this->authorize('comment', $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $ticket->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $ticket->touch();

        return redirect()->to(route('tickets.show', $ticket).'#comments')->with('success', __('tickets.comment_added'));
    }
}
