<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a bill. Title and amount are copied from the ticket when the bill is issued. */
class BillItem extends Model
{
    protected $fillable = ['bill_id', 'ticket_id', 'title', 'details', 'amount', 'is_manual', 'sort_order'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_manual' => 'boolean',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }
}
