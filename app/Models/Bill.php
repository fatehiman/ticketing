<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bill (invoice) a developer issues to a customer of a project. Its items are tickets:
 * done tickets with a cost, or manual items (monthly support, …) that are saved as done tickets,
 * so the transactions page counts them as costs like any other ticket.
 * Paid / partly paid / unpaid comes from App\Support\Bills::allocate().
 */
class Bill extends Model
{
    use SoftDeletes;

    /** Bill numbers start after this (bill id 1 = number 1001). */
    public const NUMBER_OFFSET = 1000;

    protected $fillable = ['project_id', 'customer_id', 'issued_on', 'due_on', 'description', 'total', 'created_by'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Bill $bill) {
            $bill->number = $bill->id + self::NUMBER_OFFSET;
            $bill->saveQuietly();
        });
    }

    /** URLs use the bill number: /bills/1001 */
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Admin: all. Developer: bills of their projects. Customer: their own bills. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->isCustomer()) {
            return $query->where('bills.customer_id', $user->id);
        }

        return $query->whereIn('bills.project_id', $user->accessibleProjectIds() ?: [0]);
    }
}
