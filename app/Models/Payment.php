<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Money a customer paid. Shown on the transactions page next to the costs of done tickets. */
class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = ['customer_id', 'project_id', 'amount', 'paid_on', 'description', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Admin: every payment. Developer: payments of their customers (any developer of the
     * customer may see and manage them), on one of the developer's projects or without a
     * project. Customer: only their own payments.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->isCustomer()) {
            return $query->where('payments.customer_id', $user->id);
        }

        return $query->whereIn('payments.customer_id', User::query()->customersOf($user)->select('users.id'))
            ->where(fn (Builder $w) => $w->whereNull('payments.project_id')
                ->orWhereIn('payments.project_id', $user->accessibleProjectIds()));
    }
}
