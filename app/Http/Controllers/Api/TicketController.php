<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\SprintStatus;
use App\Enums\StoryPoint;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Exceptions\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Support\Dates;
use App\Support\Duration;
use App\Support\Html;
use App\Support\TicketFilter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * Simple JSON API for bots: who am I, allowed values, list/search tickets, read one ticket, create a ticket.
 * Values like type/status/priority accept the key ("in_progress") or the Persian/English label.
 * Dates accept Gregorian (2026-09-24) or Jalali (1405/07/02). See API.md.
 */
class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function me(Request $request)
    {
        $user = $request->user();
        $token = $request->attributes->get('api_token');

        return response()->json([
            'ok' => true,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value],
            'token_expires_at' => $token->expires_at->toIso8601String(),
            'project' => $token->project ? $this->projectData($token->project) : null,
            'projects' => $token->project_id ? null : Project::query()->visibleTo($user)->active()->orderBy('name')->get()->map(fn ($p) => $this->projectData($p)),
        ]);
    }

    /** Allowed values for filters and for new tickets. */
    public function options(Request $request)
    {
        $project = $this->project($request);
        $labels = fn (array $cases) => collect($cases)->map(fn ($c) => ['key' => $c->value, 'label' => $c->label()])->values();

        return response()->json([
            'ok' => true,
            'project' => $this->projectData($project),
            'types' => $labels(TicketType::cases()),
            'statuses' => $labels(TicketStatus::cases()),
            'priorities' => $labels(TicketPriority::cases()),
            'story_points' => collect(StoryPoint::cases())->map(fn ($c) => ['key' => $c->value, 'label' => $c->label()])->values(),
            'sprints' => $project->sprints()->get()->map(fn (Sprint $s) => [
                'number' => $s->number, 'name' => $s->name, 'status' => $s->status?->value,
                'start_date' => $s->start_date?->toDateString(), 'end_date' => $s->end_date?->toDateString(),
            ]),
            'assignees' => $this->developers($project)->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $project = $this->project($request);
        $query = Ticket::query()->visibleTo($user)->where('tickets.project_id', $project->id)->with(['sprint', 'assignee', 'reporter']);

        if ($values = $this->list($request->input('status'))) {
            $query->whereIn('status', array_map(fn ($v) => $this->enum(TicketStatus::class, 'status', $v), $values));
        }
        if ($values = $this->list($request->input('type'))) {
            $query->whereIn('type', array_map(fn ($v) => $this->enum(TicketType::class, 'type', $v), $values));
        }
        if ($values = $this->list($request->input('priority'))) {
            $query->whereIn('priority', array_map(fn ($v) => $this->enum(TicketPriority::class, 'priority', $v), $values));
        }
        if (filled($request->input('sprint'))) {
            $value = trim((string) $request->input('sprint'));
            in_array(mb_strtolower($value), ['none', 'no', 'بدون'], true)
                ? $query->whereNull('sprint_id')
                : $query->where('sprint_id', $this->sprint($project, $value)->id);
        }
        if (filled($request->input('assignee'))) {
            $value = mb_strtolower(trim((string) $request->input('assignee')));
            match ($value) {
                'none' => $query->whereNull('assignee_id'),
                'me' => $query->where('assignee_id', $user->id),
                default => $query->where('assignee_id', $this->assignee($project, $value, $user)),
            };
        }
        if (filled($request->input('number'))) {
            $query->where('number', (int) Dates::latinDigits(ltrim((string) $request->input('number'), '#')));
        }
        if (filled($request->input('title'))) {
            $query->where('title', 'like', '%'.trim((string) $request->input('title')).'%');
        }
        if (filled($request->input('description'))) {
            $query->where('content', 'like', '%'.trim((string) $request->input('description')).'%');
        }
        if (filled($request->input('q'))) {
            $q = trim((string) $request->input('q'));
            $query->where(function (Builder $w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('content', 'like', "%{$q}%");
                $digits = Dates::latinDigits(ltrim($q, '#'));
                if (ctype_digit($digits)) {
                    $w->orWhere('number', (int) $digits);
                }
            });
        }

        // Date range on the created date (or updated / due date with date_field).
        $column = match ($request->input('date_field', 'created')) {
            'updated' => 'tickets.updated_at',
            'due' => 'due_date',
            'created' => 'tickets.created_at',
            default => throw new ApiError('invalid_value', 'date_field must be one of: created, updated, due.'),
        };
        if (filled($request->input('date_from'))) {
            $from = $this->date($request->input('date_from'), 'date_from');
            $query->where($column, '>=', $column === 'due_date' ? $from->toDateString() : $from);
        }
        if (filled($request->input('date_to'))) {
            $to = $this->date($request->input('date_to'), 'date_to');
            $query->where($column, '<=', $column === 'due_date' ? $to->toDateString() : $to->endOfDay());
        }

        TicketFilter::applySort($query, $request->input('sort'), $request->input('dir'));
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $page = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'project' => $this->projectData($project),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
            'tickets' => collect($page->items())->map(fn (Ticket $t) => $this->summary($t))->values(),
        ]);
    }

    public function show(Request $request, string $number)
    {
        $ticket = $this->findTicket($request, $number);

        return response()->json(['ok' => true, 'ticket' => $this->details($ticket)]);
    }

    /** Only the title is required. */
    public function store(Request $request)
    {
        $user = $request->user();
        $project = $this->project($request);

        $input = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:200000'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'sprint' => ['nullable', 'string', 'max:100'],
            'assignee' => ['nullable', 'string', 'max:100'],
            'story_points' => ['nullable', 'integer', 'in:'.implode(',', array_column(StoryPoint::cases(), 'value'))],
            'due_date' => ['nullable', 'string', 'max:20'],
            'estimated_time' => ['nullable', 'regex:'.Duration::PATTERN],
            'estimated_cost' => ['nullable', 'integer', 'min:0', 'max:9999999999999999'],
        ]);

        $data = [
            'project_id' => $project->id,
            'title' => trim($input['title']),
            'content' => $this->descriptionHtml($input['description'] ?? null),
            'type' => filled($input['type'] ?? null) ? $this->enum(TicketType::class, 'type', $input['type']) : TicketType::Task->value,
            'status' => filled($input['status'] ?? null) ? $this->enum(TicketStatus::class, 'status', $input['status']) : TicketStatus::Backlog->value,
            'priority' => filled($input['priority'] ?? null) ? $this->enum(TicketPriority::class, 'priority', $input['priority']) : TicketPriority::Medium->value,
            'sprint_id' => filled($input['sprint'] ?? null) ? $this->sprint($project, $input['sprint'])->id : null,
            'assignee_id' => filled($input['assignee'] ?? null) ? $this->assignee($project, mb_strtolower(trim($input['assignee'])), $user) : null,
            'story_points' => $input['story_points'] ?? null,
            'due_date' => filled($input['due_date'] ?? null) ? $this->date($input['due_date'], 'due_date')->toDateString() : null,
            'estimated_minutes' => Duration::toMinutes($input['estimated_time'] ?? null),
            'estimated_cost' => $input['estimated_cost'] ?? null,
        ];

        $ticket = $this->tickets->create($data, $user);

        return response()->json(['ok' => true, 'message' => 'Ticket #'.$ticket->number.' created.', 'ticket' => $this->details($ticket->fresh())], 201);
    }

    // ---------------------------------------------------------------------

    /** The token's project, or the "project" input (id or code) when the token has no fixed project. */
    private function project(Request $request): Project
    {
        $user = $request->user();
        $token = $request->attributes->get('api_token');
        $value = trim(Dates::latinDigits((string) $request->input('project')));

        if ($token->project_id) {
            $project = $token->project;
            if (! $project || ! $user->canAccessProject($project)) {
                throw new ApiError('forbidden', 'You no longer have access to the project of this token. Log in again: POST /api/auth/start.', 403);
            }
            if ($value !== '' && $value !== (string) $project->id && mb_strtolower($value) !== mb_strtolower($project->code)) {
                throw new ApiError('wrong_project', "This token is linked to project {$project->code} ({$project->name}). Do not send another project.");
            }

            return $project;
        }

        if ($value === '') {
            throw new ApiError('project_required', 'This token has no fixed project. Send "project" (id or code). GET /api/me lists your projects.');
        }
        $project = Project::query()->visibleTo($user)
            ->where(fn ($q) => ctype_digit($value) ? $q->where('id', (int) $value)->orWhere('code', $value) : $q->where('code', $value))
            ->first();

        return $project ?? throw new ApiError('project_not_found', "Project \"{$value}\" was not found or you have no access. GET /api/me lists your projects.", 404);
    }

    private function findTicket(Request $request, string $number): Ticket
    {
        $user = $request->user();
        $token = $request->attributes->get('api_token');
        $number = (int) Dates::latinDigits(ltrim($number, '#'));

        $ticket = Ticket::query()->visibleTo($user)->where('number', $number)
            ->when($token->project_id, fn ($q) => $q->where('tickets.project_id', $token->project_id))
            ->with(['project', 'sprint', 'assignee', 'reporter'])->withCount(['followups', 'attachments'])
            ->first();

        return $ticket ?? throw new ApiError('not_found', "Ticket #{$number} was not found.", 404);
    }

    /** A backed enum value from its key or its fa/en label. */
    private function enum(string $enum, string $group, mixed $value): string
    {
        $norm = fn (string $s) => str_replace([' ', '-', "\u{200C}"], '_', mb_strtolower(trim($s)));
        $wanted = $norm(Dates::latinDigits((string) $value));

        foreach ($enum::cases() as $case) {
            $names = [$case->value, __("enums.{$group}.{$case->value}", [], 'fa'), __("enums.{$group}.{$case->value}", [], 'en')];
            if (in_array($wanted, array_map($norm, $names), true)) {
                return $case->value;
            }
        }
        $allowed = implode(', ', array_column($enum::cases(), 'value'));

        throw new ApiError('invalid_value', "Unknown {$group} \"{$value}\". Allowed: {$allowed}.");
    }

    /** Sprint by number, "active" (the current sprint) or name. */
    private function sprint(Project $project, string $value): Sprint
    {
        $value = trim(Dates::latinDigits($value));
        $sprints = $project->sprints();

        $sprint = match (true) {
            in_array(mb_strtolower($value), ['active', 'current', 'فعال', 'جاری'], true) => $sprints->where('status', SprintStatus::Active->value)->first(),
            ctype_digit($value) => $sprints->where('number', (int) $value)->first(),
            default => $sprints->where('name', 'like', "%{$value}%")->first(),
        };

        return $sprint ?? throw new ApiError('sprint_not_found', "Sprint \"{$value}\" was not found in project {$project->code}. Use the sprint number, \"active\" or \"none\". GET /api/options lists the sprints.", 404);
    }

    /** Assignee id: "me", a user id or part of a developer's name. */
    private function assignee(Project $project, string $value, User $user): int
    {
        $developers = $this->developers($project);
        $value = Dates::latinDigits($value);

        $found = match (true) {
            $value === 'me' => $developers->firstWhere('id', $user->id),
            ctype_digit($value) => $developers->firstWhere('id', (int) $value),
            default => $developers->first(fn (User $u) => str_contains(mb_strtolower($u->name), $value)),
        };

        return $found?->id ?? throw new ApiError('assignee_not_found', "Assignee \"{$value}\" is not a developer of project {$project->code}. GET /api/options lists the assignees.", 404);
    }

    private function developers(Project $project)
    {
        return $project->members()->where('role', Role::Developer->value)->orderBy('first_name')->get();
    }

    private function date(mixed $value, string $field): Carbon
    {
        return Dates::parse((string) $value)
            ?? throw new ApiError('invalid_value', "{$field} must be a date like 2026-09-24 (Gregorian) or 1405/07/02 (Jalali).");
    }

    /** "a,b" / "a، b" / ["a","b"] → ["a","b"] */
    private function list(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[,،]/u', (string) $value);

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
    }

    /** Plain text becomes simple HTML; HTML is cleaned like editor content. */
    private function descriptionHtml(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        if (preg_match('#</?(p|br|div|span|b|i|u|strong|em|ul|ol|li|a|h[1-6]|table|tr|td|img|pre|code|blockquote)\b[^>]*>#i', $text)) {
            return $text; // cleaned by TicketService
        }
        $paragraphs = preg_split("/\R{2,}/u", trim($text));

        return implode('', array_map(fn ($p) => '<p>'.nl2br(e($p), false).'</p>', $paragraphs));
    }

    private function projectData(Project $project): array
    {
        return ['id' => $project->id, 'code' => $project->code, 'name' => $project->name];
    }

    private function summary(Ticket $t): array
    {
        return [
            'number' => $t->number,
            'title' => $t->title,
            'type' => $t->type?->value,
            'type_label' => $t->type?->label(),
            'status' => $t->status?->value,
            'status_label' => $t->status?->label(),
            'priority' => $t->priority?->value,
            'priority_label' => $t->priority?->label(),
            'sprint' => $t->sprint?->number,
            'sprint_name' => $t->sprint?->name,
            'assignee' => $t->assignee?->name,
            'reporter' => $t->reporter?->name,
            'due_date' => $t->due_date?->toDateString(),
            'created_at' => $this->when($t->created_at),
            'created_at_jalali' => $this->jalali($t->created_at),
            'updated_at' => $this->when($t->updated_at),
            'url' => route('tickets.show', $t),
        ];
    }

    private function details(Ticket $t): array
    {
        $t->loadMissing(['project', 'sprint', 'assignee', 'reporter']);
        $t->loadCount(['followups', 'attachments']);

        return array_merge($this->summary($t), [
            'project' => $this->projectData($t->project),
            'description' => Html::toText($t->content),
            'story_points' => $t->story_points,
            'done_story_points' => $t->done_story_points,
            'estimated_time' => $t->estimated_minutes !== null ? Duration::format($t->estimated_minutes) : null,
            'logged_time' => $t->logged_minutes !== null ? Duration::format($t->logged_minutes) : null,
            'estimated_cost' => $t->estimated_cost,
            'cost' => $t->cost,
            'currency' => $t->project->currency,
            'due_date_jalali' => $t->due_date ? Jalalian::fromCarbon($t->due_date)->format('Y/m/d') : null,
            'resolved_at' => $this->when($t->resolved_at),
            'awaiting_reply_from' => $t->awaiting_reply,
            'followups_count' => $t->followups_count,
            'attachments_count' => $t->attachments_count,
        ]);
    }

    private function when(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i');
    }

    private function jalali(?CarbonInterface $date): ?string
    {
        return $date ? Jalalian::fromCarbon(Carbon::instance($date)->setTimezone(config('app.timezone')))->format('Y/m/d H:i') : null;
    }
}
