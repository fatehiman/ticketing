<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bot API: "Authorization: Bearer <token>". The token user must still be an active developer or admin.
 * Any failure is a 401 "auth_required", so the bot knows it must run the login steps again.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findValid($request->bearerToken());
        $user = $token?->user;

        if (! $token || ! $user || ! $user->is_active || ! $user->isStaff()) {
            return response()->json([
                'ok' => false,
                'error' => 'auth_required',
                'message' => 'API token is missing, wrong or expired. Log in again: call POST /api/auth/start and follow the steps.',
            ], 401);
        }

        // Write last_used_at at most once a minute.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        Auth::setUser($user);
        app()->setLocale($user->locale ?: config('app.locale'));
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
