<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Go back to where I came from" after saving a form.
 *
 * The browser (resources/js/app.js) keeps the last *list* page of each tab (a folder, a filtered list,
 * users…) in sessionStorage and sends it with every POST form as "_back". Detail pages (*.show) and
 * form pages (*.create / *.edit) do not change it, so list → ticket → edit → save lands on the list again.
 */
class ReturnTo
{
    public const FIELD = '_back';

    /** "form", "detail" or "list", from the route name. */
    public static function kind(?string $routeName): string
    {
        return match (Str::afterLast((string) $routeName, '.')) {
            'create', 'edit' => 'form',
            'show' => 'detail',
            default => 'list',
        };
    }

    /** The referrer, when it is a list page of this site (used when a tab starts on a detail/form page). */
    public static function listReferrer(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer');
        if (! self::isLocal($referer)) {
            return null;
        }
        try {
            $route = Route::getRoutes()->match(Request::create($referer));
        } catch (Throwable) {
            return null;
        }

        // Only pages of the app itself (not the login page, locale switch, bot login…).
        $inApp = $route->getName() && in_array('auth', $route->gatherMiddleware(), true);

        return $inApp && self::kind($route->getName()) === 'list' ? $referer : null;
    }

    /** The "_back" URL of the request when it is safe (same site), otherwise null. */
    public static function from(Request $request): ?string
    {
        $back = (string) $request->input(self::FIELD);

        return self::isLocal($back) ? $back : null;
    }

    /** A full http(s) URL of this site. Compared by host, so http/https behind the CDN does not matter. */
    private static function isLocal(string $url): bool
    {
        if ($url === '' || preg_match('/[\s\\\\]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || ! isset($parts['host'])) {
            return false;
        }
        $hosts = array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);

        return in_array(strtolower($parts['host']), array_map('strtolower', $hosts), true);
    }
}
