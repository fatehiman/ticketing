<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Support\ProjectContext;
use App\Support\TicketFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProjectContext $context)
    {
        $user = $request->user();
        $base = fn () => TicketFilter::query([], $user, $context);

        $byStatus = $base()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $openStatuses = array_map(fn ($s) => $s->value, array_filter(TicketStatus::cases(), fn ($s) => ! $s->isClosed()));
        $byPriority = $base()->whereIn('status', $openStatuses)
            ->select('priority', DB::raw('count(*) as c'))->groupBy('priority')->pluck('c', 'priority');

        $totals = [
            'open' => $byStatus->only($openStatuses)->sum(),
            'in_progress' => (int) ($byStatus[TicketStatus::InProgress->value] ?? 0),
            'done' => (int) ($byStatus[TicketStatus::Done->value] ?? 0),
            'all' => $byStatus->sum(),
            'mine' => $user->isStaff() ? $base()->where('assignee_id', $user->id)->whereIn('status', $openStatuses)->count() : null,
            'overdue' => $base()->whereIn('status', $openStatuses)->whereNotNull('due_date')->whereDate('due_date', '<', today())->count(),
        ];

        $projectIds = $context->scopeIds() ?? $user->accessibleProjectIds();
        $activeSprints = Sprint::with('project')
            ->where('status', 'active')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->withCount(['tickets', 'tickets as done_count' => fn ($q) => $q->where('status', TicketStatus::Done->value)])
            ->withSum('tickets as points_total', 'story_points')
            ->withSum('tickets as points_done', 'done_story_points')
            ->limit(6)->get();

        $recent = $base()->with(['project', 'assignee', 'reporter'])->latest('updated_at')->limit(8)->get();

        $projectCount = Project::query()->visibleTo($user)->active()->count();

        return view('dashboard', [
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'totals' => $totals,
            'recent' => $recent,
            'activeSprints' => $activeSprints,
            'projectCount' => $projectCount,
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
        ]);
    }
}
