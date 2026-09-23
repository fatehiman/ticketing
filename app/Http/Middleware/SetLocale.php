<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uses the user's language (or the guest's session choice). Persian is the default. */
class SetLocale
{
    public const LOCALES = ['fa', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', config('app.locale'));
        if (! in_array($locale, self::LOCALES, true)) {
            $locale = config('app.locale');
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
