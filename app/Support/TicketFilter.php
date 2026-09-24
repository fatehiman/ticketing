<?php

namespace App\Support;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ticket search filters. The same filter set is used by the search page,
 * the built-in folders and the user's custom folders (cartables).
 */
class TicketFilter
{
    /** Keys that define "which tickets" (used to match a menu). */
    public const KEYS = [
        'q', 'number', 'project_id', 'sprint_id', 'status', 'priority', 'type', 'assignee_id', 'reporter_id',
        'awaiting', 'created_from', 'created_to', 'updated_from', 'updated_to', 'due_from', 'due_to',
    ];

    public const ARRAY_KEYS = ['status', 'priority', 'type'];

    public const SORTS = ['number', 'title', 'status', 'priority', 'created_at', 'updated_at', 'due_date', 'story_points'];

    /**
     * Keep only known keys, drop empty values, sort arrays and keys so two
     * equal filter sets always produce the same array.
     */
    public static function normalize(array $input): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $value = $input[$key] ?? null;
            if (in_array($key, self::ARRAY_KEYS, true)) {
                $value = array_values(array_unique(array_filter((array) $value, fn ($v) => $v !== null && $v !== '')));
                sort($value);
                if ($value) {
                    $out[$key] = array_map('strval', $value);
                }
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $out[$key] = trim((string) $value);
            }
        }
        ksort($out);

        return $out;
    }

    public static function matches(array $a, array $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }

    /** Build the query for a filter set, inside what the user may see and the top-bar project. */
    public static function query(array $filters, User $user, ProjectContext $context): Builder
    {
        $f = self::normalize($filters);
        $query = Ticket::query()->visibleTo($user);

        // An explicit project in the filter wins over the project picked in the top bar.
        if (isset($f['project_id']) && $user->canAccessProject((int) $f['project_id'])) {
            $query->where('tickets.project_id', (int) $f['project_id']);
        } elseif ($ids = $context->scopeIds()) {
            $query->whereIn('tickets.project_id', $ids);
        }

        if (isset($f['q'])) {
            $q = $f['q'];
            $query->where(function (Builder $w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('content', 'like', "%{$q}%");
                $digits = Dates::latinDigits(ltrim($q, '#'));
                if (ctype_digit($digits)) {
                    $w->orWhere('number', (int) $digits);
                }
            });
        }
        if (isset($f['number'])) {
            $query->where('number', (int) Dates::latinDigits(ltrim($f['number'], '#')));
        }
        if (isset($f['sprint_id'])) {
            $f['sprint_id'] === 'none' ? $query->whereNull('sprint_id') : $query->where('sprint_id', (int) $f['sprint_id']);
        }
        foreach (self::ARRAY_KEYS as $key) {
            if (isset($f[$key])) {
                $query->whereIn($key, $f[$key]);
            }
        }
        if (isset($f['assignee_id'])) {
            match ($f['assignee_id']) {
                'me' => $query->where('assignee_id', $user->id),
                'none' => $query->whereNull('assignee_id'),
                default => $query->where('assignee_id', (int) $f['assignee_id']),
            };
        }
        if (isset($f['reporter_id'])) {
            $query->where('reporter_id', $f['reporter_id'] === 'me' ? $user->id : (int) $f['reporter_id']);
        }

        // "me" = tickets where the user's side (staff or customer) must answer a followup.
        if (($f['awaiting'] ?? null) === 'me') {
            $query->where('awaiting_reply', $user->replySide());
        }

        foreach (['created' => 'tickets.created_at', 'updated' => 'tickets.updated_at', 'due' => 'due_date'] as $prefix => $column) {
            if ($from = Dates::parse($f[$prefix.'_from'] ?? null)) {
                $query->where($column, '>=', $prefix === 'due' ? $from->toDateString() : $from);
            }
            if ($to = Dates::parse($f[$prefix.'_to'] ?? null)) {
                $query->where($column, '<=', $prefix === 'due' ? $to->toDateString() : $to->endOfDay());
            }
        }

        return $query;
    }

    public static function applySort(Builder $query, ?string $sort, ?string $dir): Builder
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'updated_at';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        if ($sort === 'priority') {
            $case = collect(TicketPriority::cases())->map(fn ($p) => "WHEN '{$p->value}' THEN {$p->weight()}")->implode(' ');

            return $query->orderByRaw("CASE priority {$case} END {$dir}")->orderByDesc('tickets.id');
        }

        return $query->orderBy('tickets.'.$sort, $dir)->orderByDesc('tickets.id');
    }

    /** Validation-friendly lists for the filter form. */
    public static function options(): array
    {
        return [
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'types' => TicketType::cases(),
        ];
    }
}
