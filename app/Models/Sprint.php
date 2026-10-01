<?php

namespace App\Models;

use App\Enums\SprintStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    protected $fillable = ['project_id', 'number', 'name', 'goal', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return [
            'status' => SprintStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Sprints whose dates include today (both ends count). Found by the dates only, not by the status.
     * Sprints without both dates are never current.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->whereNotNull('start_date')->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today);
    }

    /** Same rule as scopeCurrent(): today is inside the dates (both ends count). Shown in green everywhere. */
    public function isCurrent(): bool
    {
        if (! $this->start_date || ! $this->end_date) {
            return false;
        }
        $today = now()->toDateString();

        return $this->start_date->toDateString() <= $today && $this->end_date->toDateString() >= $today;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Date conflicts between sprints of the same project (only a warning, saving is never blocked).
     * Two sprints conflict when their days overlap (both ends count: a sprint that ends on the 12th
     * and the next one that starts on the 12th conflict). Gaps are fine. Sprints without both dates are ignored.
     * For each pair the end of the earlier sprint and the start of the later sprint are marked.
     *
     * @param  int[]  $projectIds
     * @return array<int,array{start?:string[],end?:string[]}> sprint id => side => labels of the other sprints
     */
    public static function dateConflicts(array $projectIds): array
    {
        $out = [];
        $byProject = self::whereIn('project_id', $projectIds)->whereNotNull('start_date')->whereNotNull('end_date')
            ->orderBy('start_date')->orderBy('id')->get()->groupBy('project_id');

        foreach ($byProject as $sprints) {
            $sprints = $sprints->values();
            foreach ($sprints as $i => $a) {
                foreach ($sprints->slice($i + 1) as $b) {
                    if ($b->start_date->gt($a->end_date)) {
                        continue; // sorted by start: $b starts after $a ends, no overlap
                    }
                    $out[$a->id]['end'][] = $b->label();
                    $out[$b->id]['start'][] = $a->label();
                }
            }
        }

        return $out;
    }

    /** Labels of the sprints this sprint conflicts with (for the warning after saving). */
    public function conflictingLabels(): array
    {
        $conflicts = self::dateConflicts([$this->project_id])[$this->id] ?? [];

        return array_values(array_unique(array_merge($conflicts['start'] ?? [], $conflicts['end'] ?? [])));
    }

    /** Length in calendar days, both ends included (weekends count too): 5th → 12th = 8 days. Null without both dates. */
    public function days(): ?int
    {
        if (! $this->start_date || ! $this->end_date || $this->end_date->lt($this->start_date)) {
            return null;
        }

        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    /** "Sprint 4 — Checkout" */
    public function label(): string
    {
        return __('sprints.sprint_n', ['n' => $this->number]).($this->name ? ' — '.$this->name : '');
    }
}
