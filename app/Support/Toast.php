<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserToast;

/**
 * Toast messages (like Filament notifications): a small card at the top corner that closes by itself.
 *
 * - Toast::to($user, 'Saved')        — for any user: shown on their next page or by polling (GET /toasts,
 *   every 15 seconds while a page is open). Not shown within TTL seconds = the user was not online: dropped.
 * - window.toast({...}) in app.js     — the same card from JavaScript.
 *
 * Types: success, info, warning, danger. Options: title, icon (bootstrap-icons name), url + link, duration (ms).
 */
class Toast
{
    /** Seconds a toast waits for its user. */
    public const TTL = 120;

    public static function to(?User $user, string $body, string $type = 'info', array $options = []): void
    {
        if (! $user) {
            return;
        }
        UserToast::create([
            'user_id' => $user->id,
            'data' => array_filter(['type' => $type, 'body' => $body] + $options, fn ($v) => $v !== null),
        ]);
    }

    /** Toasts of the page being shown: the login toast (tickets waiting for a reply) + the user's waiting toasts. */
    public static function forPage(): array
    {
        $user = auth()->user();
        $toasts = $user ? self::pull($user) : [];
        if ($count = session('awaiting_toast')) {
            array_unshift($toasts, [
                'type' => 'danger', 'icon' => 'reply-fill', 'duration' => 10000,
                'body' => trans_choice('tickets.followup.toast', $count, ['count' => $count]),
                'url' => route('tickets.index', ['awaiting' => 'me']), 'link' => __('tickets.followup.toast_link'),
            ]);
        }

        return $toasts;
    }

    /** The user's waiting toasts (oldest first). They are deleted: each toast is shown once. */
    public static function pull(User $user): array
    {
        UserToast::where('created_at', '<', now()->subSeconds(self::TTL))->delete();

        $toasts = UserToast::where('user_id', $user->id)->orderBy('id')->get();
        if ($toasts->isEmpty()) {
            return [];
        }
        UserToast::whereKey($toasts->modelKeys())->delete();

        return $toasts->pluck('data')->all();
    }
}
