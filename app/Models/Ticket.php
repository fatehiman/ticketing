<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    /** Fields that are tracked in the revision history. */
    public const TRACKED = [
        'project_id', 'sprint_id', 'type', 'status', 'priority', 'title', 'content', 'assignee_id',
        'story_points', 'done_story_points', 'estimated_minutes', 'logged_minutes',
        'estimated_cost', 'cost', 'due_date',
    ];

    protected $fillable = [
        'project_id', 'sprint_id', 'type', 'status', 'priority', 'title', 'content', 'reporter_id',
        'assignee_id', 'story_points', 'done_story_points', 'estimated_minutes', 'logged_minutes',
        'estimated_cost', 'cost', 'due_date', 'resolved_at', 'updated_by', 'deleted_by',
        'awaiting_reply', 'awaiting_since',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'type' => TicketType::class,
            'due_date' => 'date',
            'resolved_at' => 'datetime',
            'awaiting_since' => 'datetime',
            'estimated_cost' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Ticket number = incremental id followed by 2 random digits, e.g. id 12 → 1247.
        static::created(function (Ticket $ticket) {
            $ticket->number = $ticket->id * 100 + random_int(10, 99);
            $ticket->saveQuietly();
        });
    }

    /** URLs use the ticket number: /tickets/1047 */
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TicketRevision::class)->latest('id');
    }

    /** The customer's rating (only on closed tickets). */
    public function comment(): HasOne
    {
        return $this->hasOne(TicketComment::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(TicketFollowup::class)->oldest('id');
    }

    /** Files of the ticket itself (not of its followups). */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->whereNull('followup_id');
    }

    /** True when the given user's side (staff or customer) must answer this ticket. */
    public function isAwaiting(User $user): bool
    {
        return $this->awaiting_reply !== null && $this->awaiting_reply === $user->replySide();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->accessibleProjectIds();

        return $ids === null ? $query : $query->whereIn('tickets.project_id', $ids);
    }
}
