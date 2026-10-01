<?php

namespace App\Support;

use App\Enums\StoryPoint;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Turns raw values stored in ticket_revisions into readable text. */
class RevisionPresenter
{
    private static array $cache = [];

    /** Changes of these fields (price, time, story points) are not shown to customers in the history. */
    public const STAFF_ONLY = ['story_points', 'done_story_points', 'estimated_minutes', 'logged_minutes', 'estimated_cost', 'cost'];

    /**
     * The changes of a revision that this user may see. Null = hide the whole entry
     * (it had changes, but none of them is for this user).
     */
    public static function visibleChanges(?array $changes, User $user): ?array
    {
        $changes ??= [];
        if ($user->isStaff()) {
            return $changes;
        }
        $visible = array_diff_key($changes, array_flip(self::STAFF_ONLY));

        return $changes !== [] && $visible === [] ? null : $visible;
    }

    public static function field(string $field): string
    {
        return __('tickets.fields.'.$field);
    }

    public static function value(string $field, mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }

        return match ($field) {
            'status' => TicketStatus::tryFrom($raw)?->label() ?? $raw,
            'priority' => TicketPriority::tryFrom($raw)?->label() ?? $raw,
            'type' => TicketType::tryFrom($raw)?->label() ?? $raw,
            'story_points', 'done_story_points' => StoryPoint::labelFor((int) $raw),
            'estimated_minutes', 'logged_minutes' => Duration::format((int) $raw),
            'estimated_cost', 'cost' => Money::format($raw),
            'due_date' => Dates::format(Carbon::parse($raw)),
            'assignee_id' => self::$cache['u'.$raw] ??= (User::withTrashed()->find($raw)?->name ?? '#'.$raw),
            'project_id' => self::$cache['p'.$raw] ??= (Project::withTrashed()->find($raw)?->name ?? '#'.$raw),
            'sprint_id' => self::$cache['s'.$raw] ??= (Sprint::find($raw)?->label() ?? '#'.$raw),
            'content' => Html::excerpt($raw, 80),
            default => (string) $raw,
        };
    }
}
