<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The project picked in the top bar. Every page, badge and dashboard follows it.
 * null = "All projects" (only for developers and admins).
 */
class ProjectContext
{
    private ?Collection $options = null;

    private bool $resolved = false;

    private ?Project $current = null;

    private ?int $userId = null;

    private ?User $user = null;

    /** The signed-in user. Cached values are dropped when the user changes. */
    private function user(): ?User
    {
        $user = auth()->user();
        if ($user?->id !== $this->userId) {
            $this->userId = $user?->id;
            $this->options = null;
            $this->resolved = false;
            $this->current = null;
        }

        return $this->user = $user;
    }

    /** Active projects the user can pick in the switcher. */
    public function options(): Collection
    {
        if (! $this->user()) {
            return collect();
        }

        return $this->options ??= Project::query()->visibleTo($this->user)->active()->orderBy('name')->get();
    }

    public function allowAll(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function showSwitcher(): bool
    {
        return $this->options()->count() > 1;
    }

    public function current(): ?Project
    {
        if (! $this->user()) {
            return null;
        }
        if ($this->resolved) {
            return $this->current;
        }
        $this->resolved = true;

        $options = $this->options();
        $current = $options->firstWhere('id', $this->user->current_project_id);

        // Customers (and staff with a single project) always work inside one project.
        if (! $current && (! $this->allowAll() || $options->count() === 1)) {
            $current = $options->first();
        }

        return $this->current = $current;
    }

    public function id(): ?int
    {
        return $this->current()?->id;
    }

    /** Project IDs for scoping queries: [current] or null (= every project the user can see). */
    public function scopeIds(): ?array
    {
        return $this->id() ? [$this->id()] : null;
    }

    public function set(?int $projectId): void
    {
        if ($projectId !== null && ! $this->options()->contains('id', $projectId)) {
            abort(403);
        }
        if ($projectId === null && ! $this->allowAll()) {
            abort(403);
        }
        $this->user()->forceFill(['current_project_id' => $projectId])->save();
        $this->resolved = false;
    }
}
