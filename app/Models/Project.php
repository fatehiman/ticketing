<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'logo_path', 'status', 'start_date', 'end_date', 'budget',
        'currency', 'phone1', 'phone2', 'email', 'website', 'contact_person', 'address', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function developers(): BelongsToMany
    {
        return $this->members()->where('role', Role::Developer->value);
    }

    public function customers(): BelongsToMany
    {
        return $this->members()->where('role', Role::Customer->value);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderByDesc('number');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Active->value);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id));
    }
}
