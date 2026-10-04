<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money a customer paid (a bank voucher). A customer registers it as *pending*; a developer
 * accepts or declines it (payments added by a developer are accepted at once).
 * Only accepted payments are shown on the transactions page next to the costs of done tickets.
 */
class Payment extends Model
{
    use SoftDeletes;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const STATUSES = [self::PENDING, self::ACCEPTED, self::DECLINED];

    protected $fillable = [
        'customer_id', 'project_id', 'bill_id', 'amount', 'status', 'paid_on', 'paid_time', 'reference_no',
        'description', 'created_by', 'updated_by',
    ];

    protected $attributes = ['status' => self::ACCEPTED];

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

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class)->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public static function statusColor(string $status): string
    {
        return [self::PENDING => '#ea7d24', self::ACCEPTED => '#16a34a', self::DECLINED => '#e11d48'][$status] ?? '#64748b';
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
