<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Full access API key (all projects of the user). Only the SHA-256 hash is stored; the plain key is shown once. */
class FullApiKey extends Model
{
    protected $fillable = ['user_id', 'key_hash', 'key_hint', 'last_used_at'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Create or rotate: the old key stops working at once. @return array{0: FullApiKey, 1: string} */
    public static function rotate(User $user): array
    {
        $plain = 'tkf_'.Str::random(48);
        $key = self::updateOrCreate(['user_id' => $user->id], [
            'key_hash' => hash('sha256', $plain),
            'key_hint' => substr($plain, -4),
            'last_used_at' => null,
        ]);

        return [$key, $plain];
    }

    public static function findByPlain(?string $plain): ?self
    {
        return $plain ? self::where('key_hash', hash('sha256', $plain))->first() : null;
    }
}
