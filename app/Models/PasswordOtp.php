<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A password recovery code sent to a mobile number. A resend sends the SAME code (so a late
 * first SMS still works) and gives it 2 more minutes. The code is stored encrypted, because a
 * resend needs the plain value.
 */
class PasswordOtp extends Model
{
    protected $fillable = ['user_id', 'mobile', 'code', 'sends', 'attempts', 'last_sent_at', 'expires_at', 'verified_at', 'used_at'];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return [
            'code' => 'encrypted',
            'last_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
