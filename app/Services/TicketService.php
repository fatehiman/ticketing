<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketRevision;
use App\Models\User;
use App\Support\Html;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All writes to tickets go through here, so every change is kept in
 * ticket_revisions and nothing is really deleted.
 */
class TicketService
{
    public function create(array $data, User $user, array $files = []): Ticket
    {
        return DB::transaction(function () use ($data, $user, $files) {
            $data['content'] = Html::clean($data['content'] ?? null);
            $ticket = new Ticket($data);
            $ticket->reporter_id = $user->id;
            $ticket->updated_by = $user->id;
            if ($ticket->status === TicketStatus::Done) {
                $ticket->resolved_at = now();
            }
            $ticket->save();

            $this->storeFiles($ticket, $files, $user);
            $this->record($ticket, $user, 'created', null);

            return $ticket;
        });
    }

    public function update(Ticket $ticket, array $data, User $user, array $files = [], string $action = 'updated'): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $user, $files, $action) {
            if (array_key_exists('content', $data)) {
                $data['content'] = Html::clean($data['content']);
            }
            $ticket->fill(Arr::only($data, Ticket::TRACKED));

            if ($ticket->isDirty('status')) {
                $ticket->resolved_at = $ticket->status === TicketStatus::Done ? now() : null;
            }

            $changes = [];
            foreach (array_intersect_key($ticket->getDirty(), array_flip(Ticket::TRACKED)) as $field => $new) {
                $changes[$field] = ['old' => $ticket->getRawOriginal($field), 'new' => $ticket->getAttributes()[$field] ?? null];
            }

            $stored = $this->storeFiles($ticket, $files, $user);
            if ($stored) {
                $changes['attachments'] = ['old' => null, 'new' => implode(', ', $stored)];
            }

            if ($changes) {
                $ticket->updated_by = $user->id;
                $ticket->save();
                $this->record($ticket, $user, $action, $changes);
            }

            return $ticket;
        });
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status, User $user): Ticket
    {
        return $this->update($ticket, ['status' => $status->value], $user, [], 'status_changed');
    }

    /** Soft delete: the row stays in the database with who deleted it. */
    public function delete(Ticket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user) {
            $ticket->forceFill(['deleted_by' => $user->id])->saveQuietly();
            $this->record($ticket, $user, 'deleted', null);
            $ticket->delete();
        });
    }

    public function removeAttachment(Attachment $attachment, User $user): void
    {
        DB::transaction(function () use ($attachment, $user) {
            $ticket = $attachment->ticket;
            $attachment->delete(); // soft delete, the file stays on disk
            $this->record($ticket, $user, 'attachment_removed', [
                'attachments' => ['old' => $attachment->original_name, 'new' => null],
            ]);
            $ticket->forceFill(['updated_by' => $user->id])->touch();
        });
    }

    /** @param  UploadedFile[]  $files  @return string[] stored original names */
    private function storeFiles(Ticket $ticket, array $files, User $user): array
    {
        $names = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('attachments/'.now()->format('Y/m'), Str::random(40).($ext ? '.'.$ext : ''), 'local');
            $ticket->attachments()->create([
                'user_id' => $user->id,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
            $names[] = $file->getClientOriginalName();
        }

        return $names;
    }

    private function record(Ticket $ticket, User $user, string $action, ?array $changes): void
    {
        TicketRevision::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => $action,
            'changes' => $changes,
            'snapshot' => Arr::only($ticket->fresh()?->getAttributes() ?? $ticket->getAttributes(), array_merge(
                ['number', 'reporter_id', 'resolved_at'], Ticket::TRACKED
            )),
        ]);
    }
}
