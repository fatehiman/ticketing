<?php

namespace App\Providers;

use App\Support\ProjectContext;
use App\Support\TicketFilter;
use App\Support\TicketMenus;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One project context per request, for the signed-in user.
        $this->app->scoped(ProjectContext::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // The layout (and the tickets page) need the project switcher and the ticket folders with badges.
        // Computed once per request and kept on the request.
        View::composer(['layouts.app', 'tickets.index', 'sprints.index'], function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $request = request();
            if (! $request->attributes->has('layout_data')) {
                $context = app(ProjectContext::class);
                $current = $request->routeIs('tickets.index') ? $request->query() : null;
                $request->attributes->set('layout_data', [
                    'projectContext' => $context,
                    'ticketMenus' => (new TicketMenus($user, $context))->items($current),
                    'currentFilters' => $current !== null ? TicketFilter::normalize($current) : null,
                ]);
            }
            $view->with($request->attributes->get('layout_data'));
        });
    }
}
