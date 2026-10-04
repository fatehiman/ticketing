<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Finds a public profile picture for a user's email on free avatar services and saves it as the
 * user's avatar. Runs right after a user form is saved (no queue): the services are tried in order,
 * the first picture found wins, and after 10 seconds in total we give up quietly.
 */
class PublicAvatar
{
    public const TIME_LIMIT = 10;

    private const MAX_BYTES = 2 * 1024 * 1024;

    /** Only for a user with an email and no avatar. Returns true when a picture was saved. */
    public static function fill(User $user): bool
    {
        if (! config('services.avatar_lookup.enabled') || $user->avatar_path || ! $user->email) {
            return false;
        }

        $email = Str::lower(trim($user->email));
        $md5 = md5($email);
        $urls = [
            'https://www.gravatar.com/avatar/'.$md5.'?s=256&d=404',            // Gravatar
            'https://seccdn.libravatar.org/avatar/'.$md5.'?s=256&d=404',       // Libravatar
            'https://unavatar.io/'.rawurlencode($email).'?fallback=false',     // unavatar (other public sources)
        ];

        $deadline = microtime(true) + self::TIME_LIMIT;
        foreach ($urls as $url) {
            $left = $deadline - microtime(true);
            if ($left < 1) {
                break;
            }
            try {
                $response = Http::timeout((int) min(5, floor($left)))->connectTimeout(3)->get($url);
            } catch (\Throwable $e) {
                continue; // service down or too slow: try the next one
            }

            $type = strtolower(strtok((string) $response->header('Content-Type'), ';'));
            $body = $response->body();
            $ext = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$type] ?? null;
            if (! $response->successful() || ! $ext || $body === '' || strlen($body) > self::MAX_BYTES) {
                continue;
            }

            $path = 'avatars/'.Str::random(40).'.'.$ext;
            Storage::disk('public')->put($path, $body);
            $user->forceFill(['avatar_path' => $path])->saveQuietly();
            Log::info('avatar.public_found', ['user' => $user->id, 'source' => parse_url($url, PHP_URL_HOST)]);

            return true;
        }

        return false;
    }
}
