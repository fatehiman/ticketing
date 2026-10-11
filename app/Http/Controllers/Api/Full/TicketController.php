<?php

namespace App\Http\Controllers\Api\Full;

use App\Enums\StoryPoint;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Exceptions\ApiError;
use App\Http\Controllers\Api\TicketController as BotTicketController;
use App\Models\Project;
use App\Models\Ticket;
use App\Support\Dates;
use App\Support\Duration;
use App\Support\ProjectLookup;
use Illuminate\Http\Request;

/**
 * Full access API: the same ticket JSON as the bot API, but the project always comes from the payload
 * ("project": id, code or name) and the key reaches every project of the user. Adds update and delete.
 * list / options / create need "project"; read / update / delete by ticket number do not (a number is unique).
 */
class TicketController extends BotTicketController
{
    public function me(Request $request)
    {
        $user = $request->user();
        $key = $request->attributes->get('full_api_key');

        return response()->json([
            'ok' => true,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value],
            'key_hint' => '…'.$key->key_hint,
            'projects' => Project::query()->visibleTo($user)->orderBy('name')->get()->map(fn ($p) => $this->projectData($p))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow($request, 'create', Ticket::class);

        return parent::store($request);
    }

    /** Partial update: only the sent fields change. Send null (or "none" for sprint / assignee) to clear a field. */
    public function update(Request $request, string $number)
    {
        $user = $request->user();
        $ticket = $this->findTicket($request, $number);
        $this->allow($request, 'update', $ticket);

        $input = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
            'priority' => ['sometimes', 'required', 'string', 'max:50'],
            'sprint' => ['sometimes', 'nullable', 'string', 'max:100'],
            'assignee' => ['sometimes', 'nullable', 'string', 'max:100'],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'story_points' => ['sometimes', 'nullable', 'integer', 'in:'.implode(',', array_column(StoryPoint::cases(), 'value'))],
            'done_story_points' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'due_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'estimated_time' => ['sometimes', 'nullable', 'regex:'.Duration::PATTERN],
            'logged_time' => ['sometimes', 'nullable', 'regex:'.Duration::PATTERN],
            'estimated_cost' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999999999999'],
            'cost' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999999999999'],
        ]);

        $none = fn ($v) => $v === null || in_array(mb_strtolower(trim((string) $v)), ['none', 'no', 'بدون'], true);
        $data = [];
        if (array_key_exists('title', $input)) {
            $data['title'] = trim($input['title']);
        }
        if (array_key_exists('description', $input)) {
            $data['content'] = $this->descriptionHtml($input['description']);
        }
        foreach ([['type', TicketType::class], ['status', TicketStatus::class], ['priority', TicketPriority::class]] as [$field, $enum]) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->enum($enum, $field, $input[$field]);
            }
        }
        if (array_key_exists('sprint', $input)) {
            $data['sprint_id'] = $none($input['sprint']) ? null : $this->sprint($ticket->project, $input['sprint'])->id;
        }
        foreach (['assignee', 'assignee_id'] as $field) {
            if (array_key_exists($field, $input)) {
                $data['assignee_id'] = $none($input[$field]) ? null : $this->assignee($ticket->project, (string) $input[$field], $user);
            }
        }
        foreach (['story_points', 'done_story_points', 'estimated_cost', 'cost'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }
        if (array_key_exists('due_date', $input)) {
            $data['due_date'] = filled($input['due_date']) ? $this->date($input['due_date'], 'due_date')->toDateString() : null;
        }
        foreach (['estimated_time' => 'estimated_minutes', 'logged_time' => 'logged_minutes'] as $field => $column) {
            if (array_key_exists($field, $input)) {
                $data[$column] = Duration::toMinutes($input[$field]);
            }
        }
        if (! $data) {
            throw new ApiError('validation', 'Nothing to update. Send at least one field, for example {"status": "done"}.');
        }

        $this->tickets->update($ticket, $data, $user);

        return response()->json(['ok' => true, 'message' => 'Ticket #'.$ticket->number.' updated.', 'ticket' => $this->details($ticket->fresh())]);
    }

    /** Soft delete: the ticket stays in the database and in the history. */
    public function destroy(Request $request, string $number)
    {
        $ticket = $this->findTicket($request, $number);
        $this->allow($request, 'delete', $ticket);
        $this->tickets->delete($ticket, $request->user());

        return response()->json(['ok' => true, 'message' => 'Ticket #'.$ticket->number.' deleted.']);
    }

    // ---------------------------------------------------------------------

    /** The project of the payload (id, code or name). Always required here. */
    protected function project(Request $request): Project
    {
        return ProjectLookup::find($request->user(), $request->input('project'));
    }

    /** By number only. If "project" is sent too, the ticket must belong to it. */
    protected function findTicket(Request $request, string $number): Ticket
    {
        $number = (int) Dates::latinDigits(ltrim($number, '#'));
        $ticket = Ticket::query()->visibleTo($request->user())->where('number', $number)
            ->with(['project', 'sprint', 'assignee', 'reporter'])->withCount(['followups', 'attachments'])->first()
            ?? throw new ApiError('not_found', "Ticket #{$number} was not found.", 404);

        if (filled($request->input('project')) && ProjectLookup::find($request->user(), $request->input('project'))->id !== $ticket->project_id) {
            throw new ApiError('wrong_project', "Ticket #{$number} is not in project \"{$request->input('project')}\".");
        }

        return $ticket;
    }

    private function allow(Request $request, string $ability, mixed $subject): void
    {
        if ($request->user()->cannot($ability, $subject)) {
            throw new ApiError('forbidden', 'This user is not allowed to do that (admins are read-only supervisors for tickets).', 403);
        }
    }
}
