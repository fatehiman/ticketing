<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /** Files are private: only users who can see the ticket can download them. */
    public function show(Request $request, Attachment $attachment)
    {
        $this->authorize('view', $attachment->ticket);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $headers = ['Content-Type' => $attachment->mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'];

        // Images may be shown inline; everything else is always downloaded.
        if ($request->boolean('inline') && $attachment->isImage()) {
            return Storage::disk('local')->response($attachment->path, $attachment->original_name, $headers);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, $headers);
    }

    public function destroy(Request $request, Attachment $attachment, TicketService $tickets)
    {
        $this->authorize('update', $attachment->ticket);
        $tickets->removeAttachment($attachment, $request->user());

        return back()->with('success', __('app.deleted'));
    }
}
