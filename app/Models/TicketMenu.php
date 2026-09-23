<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user-defined ticket folder ("cartable"): a saved set of filters. */
class TicketMenu extends Model
{
    protected $fillable = ['user_id', 'name', 'filters', 'sort_order'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
