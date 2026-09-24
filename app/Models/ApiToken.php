<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Bearer token of a bot. Only the SHA-256 hash is stored; the plain token is shown once. */
class ApiToken extends Model
{
    public const LIFETIME_DAYS = 30;

    protected $fillable = ['user_id', 'project_id', 'name', 'token_hash', 'last_used_at', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return array{0: ApiToken, 1: string} the saved token and its plain text value */
    public static function issue(User $user, ?int $projectId, ?string $name): array
    {
        $plain = 'tkt_'.Str::random(48);
        $token = self::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        return [$token, $plain];
    }

    public static function findValid(?string $plain): ?self
    {
        if (! $plain) {
            return null;
        }

        return self::where('token_hash', hash('sha256', $plain))->where('expires_at', '>', now())->first();
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
