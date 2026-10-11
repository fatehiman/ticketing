<?php

namespace App\Http\Middleware;

use App\Models\FullApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Full access API (/api/full): "Authorization: Bearer tkf_…". The key user must still be an active admin or developer.
 * Unlike a bot token, the key is not linked to a project: calls that need a project must send "project".
 */
class AuthenticateFullApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = FullApiKey::findByPlain($request->bearerToken());
        $user = $key?->user;

        if (! $key || ! $user || ! $user->is_active || ! $user->isStaff()) {
            return response()->json([
                'ok' => false,
                'error' => 'auth_required',
                'message' => 'Full API key is missing, wrong or rotated. Create or rotate it in Profile → Full access API key.',
            ], 401);
        }

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        Auth::setUser($user);
        app()->setLocale($user->locale ?: config('app.locale'));
        $request->attributes->set('full_api_key', $key);

        return $next($request);
    }
}
