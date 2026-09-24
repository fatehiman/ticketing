<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One login attempt of a bot:
 *  1. bot calls POST /api/auth/start → gets login_url (public, for the developer) and secret (private, for the bot)
 *  2. the developer opens login_url within OPEN_SECONDS, signs in, picks a project → approved
 *  3. bot calls POST /api/auth/token with request_id + secret → gets the token (only once)
 */
class ApiAuthRequest extends Model
{
    /** The link must be opened within this time. */
    public const OPEN_SECONDS = 60;

    /** After the link was opened, sign-in and project choice must be done within this time (from start). */
    public const APPROVE_MINUTES = 10;

    /** After approval, the bot must take the token within this time. */
    public const CLAIM_MINUTES = 10;

    protected $fillable = ['public_id', 'secret_hash', 'name', 'user_id', 'project_id', 'api_token_id', 'opened_at', 'approved_at', 'claimed_at'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'approved_at' => 'datetime',
            'claimed_at' => 'datetime',
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

    /** @return array{0: ApiAuthRequest, 1: string} the request and its plain secret */
    public static function start(?string $name): array
    {
        $secret = Str::random(48);
        $request = self::create([
            'public_id' => Str::random(32),
            'secret_hash' => hash('sha256', $secret),
            'name' => $name,
        ]);

        return [$request, $secret];
    }

    public function checkSecret(string $secret): bool
    {
        return hash_equals($this->secret_hash, hash('sha256', $secret));
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** Too late for the developer: link not opened in time, or not approved in time. */
    public function isExpiredForApproval(): bool
    {
        if ($this->isApproved()) {
            return false;
        }
        if ($this->opened_at === null && $this->created_at->lt(now()->subSeconds(self::OPEN_SECONDS))) {
            return true;
        }

        return $this->created_at->lt(now()->subMinutes(self::APPROVE_MINUTES));
    }

    /** Too late for the bot. */
    public function isExpired(): bool
    {
        if ($this->isApproved()) {
            return $this->approved_at->lt(now()->subMinutes(self::CLAIM_MINUTES));
        }

        return $this->isExpiredForApproval();
    }

    /** Record the first visit of the link (only while it is still in time). */
    public function markOpened(): void
    {
        if ($this->opened_at === null && ! $this->isExpiredForApproval()) {
            $this->forceFill(['opened_at' => now()])->save();
        }
    }
}
