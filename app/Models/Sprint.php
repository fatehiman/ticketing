<?php

namespace App\Models;

use App\Enums\SprintStatus;
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** "Sprint 4 — Checkout" */
    public function label(): string
    {
        return __('sprints.sprint_n', ['n' => $this->number]).($this->name ? ' — '.$this->name : '');
    }
}
