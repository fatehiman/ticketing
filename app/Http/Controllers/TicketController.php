<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StoryPoint;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Support\Dates;
use App\Support\Duration;
use App\Support\Grid;
use App\Support\Money;
use App\Support\ProjectContext;
use App\Support\TicketFilter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /** Allowed attachment extensions (max 10 MB each). */
    public const FILE_TYPES = [
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'log', 'md', 'csv', 'json', 'xml',
        'xls', 'xlsx', 'xlsm', 'xlsb', 'ods', 'ppt', 'pptx', 'odp',
        'zip', 'rar', '7z', 'tar', 'gz',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic',
        'mp4', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'wav', 'ogg', 'm4a',
    ];

    /** Default grid: number, title, type, status, priority, sprint, cost. Each user can change it. */
    public const HIDDEN_COLUMNS = [
        'project', 'assignee', 'reporter', 'story_points', 'done_story_points', 'estimated_time',
        'logged_time', 'estimated_cost', 'due_date', 'attachments', 'created_at', 'updated_at',
    ];

    public function __construct(private TicketService $tickets) {}

    public function index(Request $request, ProjectContext $context)
    {
        $user = $request->user();
        $filters = TicketFilter::normalize($request->query());
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true) ? (int) $request->query('per_page') : 25;

        $query = TicketFilter::query($filters, $user, $context)
            ->with(['project', 'sprint', 'assignee', 'reporter'])
            ->withCount('attachments');
        TicketFilter::applySort($query, $request->query('sort'), $request->query('dir'));

        $grid = Grid::make('tickets', [
            'number' => __('tickets.fields.number'),
            'title' => __('tickets.fields.title'),
            'project' => __('tickets.fields.project_id'),
            'type' => __('tickets.fields.type'),
            'status' => __('tickets.fields.status'),
            'priority' => __('tickets.fields.priority'),
            'sprint' => __('tickets.fields.sprint_id'),
            'assignee' => __('tickets.fields.assignee_id'),
            'reporter' => __('tickets.fields.reporter_id'),
            'story_points' => __('tickets.fields.story_points'),
            'done_story_points' => __('tickets.fields.done_story_points'),
            'estimated_time' => __('tickets.fields.estimated_minutes'),
            'logged_time' => __('tickets.fields.logged_minutes'),
            'estimated_cost' => __('tickets.fields.estimated_cost'),
            'cost' => __('tickets.fields.cost'),
            'due_date' => __('tickets.fields.due_date'),
            'attachments' => __('tickets.fields.attachments'),
            'created_at' => __('tickets.fields.created_at'),
            'updated_at' => __('tickets.fields.updated_at'),
        ], hidden: self::HIDDEN_COLUMNS, locked: ['number', 'title']);
        // Totals row: sums over all filtered tickets, not only this page.
        $grid->totals($query, [
            'story_points' => ['story_points', Grid::NUMBER],
            'done_story_points' => ['done_story_points', Grid::NUMBER],
            'estimated_time' => ['estimated_minutes', Grid::DURATION],
            'logged_time' => ['logged_minutes', Grid::DURATION],
            'estimated_cost' => ['estimated_cost', Grid::MONEY],
            'cost' => ['cost', Grid::MONEY],
        ]);

        return view('tickets.index', [
            'tickets' => $query->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'grid' => $grid,
            'perPage' => $perPage,
            'sort' => $request->query('sort', 'updated_at'),
            'dir' => $request->query('dir', 'desc'),
            'advancedOpen' => (bool) array_diff(array_keys($filters), ['q', 'status', 'project_id']),
        ] + $this->lookups($user, $context, false));
    }

    public function create(Request $request, ProjectContext $context)
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user();
        $ticket = new Ticket([
            'project_id' => $context->id() ?? $request->integer('project_id') ?: null,
            'priority' => TicketPriority::Medium,
            'type' => TicketType::Task,
            'status' => $user->isStaff() ? TicketStatus::Backlog : TicketStatus::PendingReview,
            // A developer is the assignee by default; admins and customers start unassigned.
            'assignee_id' => $user->isDeveloper() ? $user->id : null,
        ]);

        return view('tickets.create', ['ticket' => $ticket] + $this->lookups($request->user(), $context, true));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user();
        $data = $this->validated($request, $user);

        if (! $user->isStaff()) {
            $data['status'] = TicketStatus::PendingReview->value;
        }

        $ticket = $this->tickets->create($data, $user, $request->file('attachments', []));

        return $this->redirectBack($request, route('tickets.show', $ticket))->with('success', __('tickets.created', ['number' => $ticket->number]));
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $ticket->load(['project', 'sprint', 'assignee', 'reporter', 'editor', 'attachments.user', 'followups.user', 'followups.attachments', 'comment.user', 'revisions.user']);

        return view('tickets.show', ['ticket' => $ticket, 'statuses' => TicketStatus::cases()]);
    }

    public function edit(Request $request, Ticket $ticket, ProjectContext $context)
    {
        $this->authorize('update', $ticket);

        return view('tickets.edit', ['ticket' => $ticket->load('attachments')] + $this->lookups($request->user(), $context, true, $ticket));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $user = $request->user();
        $data = $this->validated($request, $user, $ticket);
        if (! $user->isStaff()) {
            unset($data['status']);
        }

        $this->tickets->update($ticket, $data, $user, $request->file('attachments', []));

        return $this->redirectBack($request, route('tickets.index'))->with('success', __('app.saved'));
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $this->authorize('delete', $ticket);
        $this->tickets->delete($ticket, $request->user());

        return $this->redirectBack($request, route('tickets.index'))->with('success', __('tickets.deleted', ['number' => $ticket->number]));
    }

    /** Staff: any status at any time. Customer: only "cancelled" on their own ticket. */
    public function status(Request $request, Ticket $ticket)
    {
        $status = TicketStatus::from($request->validate(['status' => ['required', Rule::enum(TicketStatus::class)]])['status']);

        if ($status === TicketStatus::Cancelled) {
            $this->authorize('cancel', $ticket);
        } else {
            $this->authorize('changeStatus', $ticket);
        }

        $this->tickets->changeStatus($ticket, $status, $request->user());

        return back()->with('success', __('tickets.status_changed', ['status' => $status->label()]));
    }

    // ---------------------------------------------------------------------

    private function validated(Request $request, User $user, ?Ticket $ticket = null): array
    {
        // Normalise user-friendly input before validation.
        $request->merge([
            'estimated_cost' => Money::parse($request->input('estimated_cost')),
            'cost' => Money::parse($request->input('cost')),
            'estimated_time' => Dates::latinDigits((string) $request->input('estimated_time')) ?: null,
            'logged_time' => Dates::latinDigits((string) $request->input('logged_time')) ?: null,
        ]);

        $projectIds = Project::query()->visibleTo($user)->pluck('id')->all();
        $projectId = (int) $request->input('project_id');
        $dateRule = fn ($attr, $value, $fail) => Dates::isValid($value) ? null : $fail(__('validation.date', ['attribute' => __('tickets.fields.'.$attr)]));

        $rules = [
            'project_id' => ['required', 'integer', Rule::in($projectIds)],
            'type' => ['required', Rule::enum(TicketType::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:2000000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'extensions:'.implode(',', self::FILE_TYPES)],
        ];

        if ($user->isStaff()) {
            $rules += [
                'status' => ['required', Rule::enum(TicketStatus::class)],
                'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
                'assignee_id' => ['nullable', 'integer', Rule::in(User::role(Role::Developer)->whereHas('projects', fn ($q) => $q->where('projects.id', $projectId))->pluck('id')->all())],
                'story_points' => ['nullable', 'integer', Rule::enum(StoryPoint::class)],
                'done_story_points' => ['nullable', 'integer', 'min:0', 'max:999'],
                'estimated_time' => ['nullable', 'regex:'.Duration::PATTERN],
                'logged_time' => ['nullable', 'regex:'.Duration::PATTERN],
                'estimated_cost' => ['nullable', 'integer', 'min:0', 'max:9999999999999999'],
                'cost' => ['nullable', 'integer', 'min:0', 'max:9999999999999999'],
                'due_date' => ['nullable', 'string', $dateRule],
            ];
        }

        $data = $request->validate($rules, [], [
            'estimated_time' => __('tickets.fields.estimated_minutes'),
            'logged_time' => __('tickets.fields.logged_minutes'),
            'attachments.*' => __('tickets.fields.attachments'),
        ]);

        if ($user->isStaff()) {
            $data['estimated_minutes'] = Duration::toMinutes($data['estimated_time'] ?? null);
            $data['logged_minutes'] = Duration::toMinutes($data['logged_time'] ?? null);
            $data['due_date'] = Dates::parse($data['due_date'] ?? null)?->toDateString();
            foreach (['sprint_id', 'assignee_id', 'story_points', 'done_story_points'] as $key) {
                $data[$key] = $data[$key] ?? null;
            }
            unset($data['estimated_time'], $data['logged_time']);
        }
        unset($data['attachments']);

        return $data;
    }

    /** Lists for the filter box and the ticket form. */
    private function lookups(User $user, ProjectContext $context, bool $forForm, ?Ticket $ticket = null): array
    {
        $projects = Project::query()->visibleTo($user)
            ->when($forForm, fn ($q) => $q->where(fn ($w) => $w->active()->when($ticket, fn ($t) => $t->orWhere('id', $ticket->project_id))))
            ->orderBy('name')->get();
        $projectIds = $projects->pluck('id');

        $sprints = Sprint::whereIn('project_id', $projectIds)->with('project')->orderByDesc('number')->get();

        $members = User::query()
            ->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $projectIds))
            ->with(['projects' => fn ($q) => $q->select('projects.id')])
            ->orderBy('first_name')->get();

        return [
            'projects' => $projects,
            'sprints' => $sprints,
            'developers' => $members->where('role', Role::Developer)->values(),
            'members' => $members,
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'types' => TicketType::cases(),
            'storyPoints' => StoryPoint::cases(),
            'currentProject' => $context->current(),
        ];
    }
}
