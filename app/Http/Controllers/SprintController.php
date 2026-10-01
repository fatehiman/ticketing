<?php

namespace App\Http\Controllers;

use App\Enums\SprintStatus;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Support\Dates;
use App\Support\Grid;
use App\Support\ProjectContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SprintController extends Controller
{
    public function index(Request $request, ProjectContext $context)
    {
        $user = $request->user();
        $projectIds = $context->scopeIds() ?? $user->accessibleProjectIds();

        $sprints = Sprint::with('project')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->withCount(['tickets', 'tickets as done_count' => fn ($q) => $q->where('status', TicketStatus::Done->value)])
            ->withSum('tickets as points_total', 'story_points')
            ->withSum('tickets as points_done', 'done_story_points')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")
            ->orderByDesc('start_date')->orderByDesc('number')
            ->paginate(25)->withQueryString();

        $grid = Grid::make('sprints', [
            'number' => __('sprints.fields.number'),
            'name' => __('sprints.fields.name'),
            'project' => __('sprints.fields.project_id'),
            'status' => __('sprints.fields.status'),
            'dates' => __('sprints.fields.dates'),
            'days' => __('sprints.fields.days'),
            'tickets' => __('sprints.done_total_tickets'),
            'points' => __('sprints.points'),
            'goal' => __('sprints.fields.goal'),
        ], hidden: ['goal'], locked: ['number']);

        // Overlapping dates inside a project are shown in red (checked against all sprints, not only this page).
        $conflicts = Sprint::dateConflicts($sprints->pluck('project_id')->unique()->all());

        return view('sprints.index', compact('sprints', 'grid', 'conflicts') + ['statuses' => SprintStatus::cases()]);
    }

    public function create(Request $request, ProjectContext $context)
    {
        $projects = $this->editableProjects($request);
        $projectId = $context->id() ?? $projects->first()?->id;
        $sprint = new Sprint([
            'project_id' => $projectId,
            'status' => SprintStatus::Planned,
            'number' => $projectId ? ((int) Sprint::where('project_id', $projectId)->max('number')) + 1 : 1,
        ]);

        return view('sprints.form', ['sprint' => $sprint, 'projects' => $projects, 'statuses' => SprintStatus::cases()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $project = Project::findOrFail($data['project_id']);
        $this->authorize('manageSprints', $project);
        $data['number'] ??= ((int) Sprint::where('project_id', $project->id)->max('number')) + 1;

        $sprint = Sprint::create($data);

        return $this->savedWithWarning($request, $sprint);
    }

    public function edit(Request $request, Sprint $sprint)
    {
        $this->authorize('manageSprints', $sprint->project);

        return view('sprints.form', ['sprint' => $sprint, 'projects' => $this->editableProjects($request), 'statuses' => SprintStatus::cases()]);
    }

    public function update(Request $request, Sprint $sprint)
    {
        $this->authorize('manageSprints', $sprint->project);
        $data = $this->validated($request, $sprint);
        $this->authorize('manageSprints', Project::findOrFail($data['project_id']));
        $sprint->update($data);

        return $this->savedWithWarning($request, $sprint);
    }

    public function destroy(Request $request, Sprint $sprint)
    {
        $this->authorize('manageSprints', $sprint->project);
        $sprint->delete(); // tickets keep existing, their sprint becomes empty

        return $this->redirectBack($request, route('sprints.index'))->with('success', __('app.deleted'));
    }

    /** Overlapping dates are allowed, but the user gets a warning. */
    private function savedWithWarning(Request $request, Sprint $sprint)
    {
        $redirect = $this->redirectBack($request, route('sprints.index'))->with('success', __('app.saved'));
        $others = $sprint->conflictingLabels();

        return $others ? $redirect->with('warning', __('sprints.conflict_saved', ['sprints' => implode(__('sprints.separator'), $others)])) : $redirect;
    }

    private function validated(Request $request, ?Sprint $sprint = null): array
    {
        $dateRule = fn ($attr, $value, $fail) => Dates::isValid($value) ? null : $fail(__('validation.date', ['attribute' => __('sprints.fields.'.$attr)]));
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'number' => ['nullable', 'integer', 'min:1', Rule::unique('sprints')->where('project_id', $request->integer('project_id'))->ignore($sprint?->id)],
            'name' => ['nullable', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:500'],
            'start_date' => ['nullable', 'string', $dateRule],
            'end_date' => ['nullable', 'string', $dateRule],
            'status' => ['required', Rule::enum(SprintStatus::class)],
        ]);
        $data['start_date'] = Dates::parse($data['start_date'] ?? null)?->toDateString();
        $data['end_date'] = Dates::parse($data['end_date'] ?? null)?->toDateString();

        return $data;
    }

    private function editableProjects(Request $request)
    {
        return Project::query()->visibleTo($request->user())->orderBy('name')->get()
            ->filter(fn ($p) => $request->user()->can('manageSprints', $p))->values();
    }
}
