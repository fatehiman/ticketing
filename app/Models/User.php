<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'mobile', 'password', 'role', 'avatar_path',
        'locale', 'calendar', 'current_project_id', 'is_active', 'created_by', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // ---- Relations -------------------------------------------------------

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ticketMenus(): HasMany
    {
        return $this->hasMany(TicketMenu::class)->orderBy('sort_order')->orderBy('id');
    }

    // ---- Helpers ---------------------------------------------------------

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isDeveloper(): bool
    {
        return $this->role === Role::Developer;
    }

    public function isCustomer(): bool
    {
        return $this->role === Role::Customer;
    }

    /** Admins and developers can pick "All projects" and change any ticket status. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isDeveloper();
    }

    /** Side in ticket conversations: tickets.awaiting_reply holds the side that must answer. */
    public function replySide(): string
    {
        return $this->isStaff() ? 'staff' : 'customer';
    }

    /** Per-request cache for accessibleProjectIds(). */
    protected ?array $projectIdsCache = null;

    /** IDs of all projects this user may see (null = no limit, for admins). */
    public function accessibleProjectIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return $this->projectIdsCache ??= $this->projects()->pluck('projects.id')->map(fn ($id) => (int) $id)->all();
    }

    public function flushProjectCache(): void
    {
        $this->projectIdsCache = null;
    }

    public function canAccessProject(int|Project|null $project): bool
    {
        if ($project === null) {
            return false;
        }
        $id = $project instanceof Project ? $project->id : $project;

        return $this->isAdmin() || in_array($id, $this->accessibleProjectIds(), true);
    }

    // ---- Scopes ----------------------------------------------------------

    public function scopeRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Customers a developer may see/manage: on one of their projects, or created by them. */
    public function scopeCustomersOf(Builder $query, User $developer): Builder
    {
        $projectIds = $developer->accessibleProjectIds();

        $query->where('role', Role::Customer->value);
        if ($projectIds === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($developer, $projectIds) {
            $q->where('created_by', $developer->id)
                ->orWhereHas('projects', fn (Builder $p) => $p->whereIn('projects.id', $projectIds));
        });
    }
}
