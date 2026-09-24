<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

/** The customer's rating of a closed ticket (1-5 stars, optional text). */
class CommentController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $this->authorize('comment', $ticket);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $ticket->comment()->updateOrCreate([], [
            'user_id' => $request->user()->id,
            'rating' => $data['rating'],
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
        ]);

        return redirect()->to(route('tickets.show', $ticket).'#rating')->with('success', __('tickets.rating_saved'));
    }
}
