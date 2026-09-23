@php
    $user = auth()->user();
    $builtin = collect($ticketMenus)->where('custom', false);
    $custom = collect($ticketMenus)->where('custom', true)->sortBy([['sort_order', 'asc'], ['id', 'asc']]);
    $onTickets = request()->routeIs('tickets.*');
@endphp
<aside class="sidebar">
    <a href="{{ route('dashboard') }}" class="brand">
        <span class="brand-logo"><i class="bi bi-ticket-perforated"></i></span>
        <span>{{ __('app.name') }}<small>{{ __('app.tagline') }}</small></span>
    </a>

    <nav class="pb-3">
        <a href="{{ route('dashboard') }}" @class(['side-link', 'active' => request()->routeIs('dashboard')])>
            <i class="bi bi-grid-1x2"></i><span>{{ __('app.dashboard') }}</span>
        </a>

        <div class="nav-section">{{ __('app.tickets') }}</div>
        <a href="{{ route('tickets.create') }}" @class(['side-link', 'active' => request()->routeIs('tickets.create')])>
            <i class="bi bi-plus-circle"></i><span>{{ __('app.new_ticket') }}</span>
        </a>
        <a class="side-link side-toggle" data-bs-toggle="collapse" href="#ticket-folders" role="button" aria-expanded="true">
            <i class="bi bi-folder2-open"></i><span>{{ __('app.tickets') }}</span><i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse show side-sub" id="ticket-folders">
            @foreach ($builtin as $item)
                <a href="{{ $item['url'] }}" @class(['side-link', 'active' => $item['active']])>
                    @if ($item['color'])
                        <span class="dot" style="--c: {{ $item['color'] }}"></span>
                    @else
                        <i class="bi {{ $item['icon'] }}"></i>
                    @endif
                    <span class="text-truncate">{{ $item['name'] }}</span>
                    <span class="side-badge">{{ $item['count'] }}</span>
                </a>
            @endforeach

            @if ($custom->isNotEmpty())
                <div class="nav-section ps-4">{{ __('tickets.menu.my_menus') }}</div>
                @foreach ($custom as $item)
                    <a href="{{ $item['url'] }}" @class(['side-link', 'active' => $item['active']])>
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span class="text-truncate">{{ $item['name'] }}</span>
                        <button type="button" class="menu-edit" title="{{ __('tickets.menu.edit') }}"
                                data-name="{{ $item['name'] }}" data-order="{{ $item['sort_order'] }}"
                                data-update-url="{{ route('ticket-menus.update', $item['id']) }}"
                                data-delete-url="{{ route('ticket-menus.destroy', $item['id']) }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <span class="side-badge">{{ $item['count'] }}</span>
                    </a>
                @endforeach
            @endif
        </div>

        @if ($user->isStaff())
            <div class="nav-section">{{ __('app.management') }}</div>
            <a href="{{ route('projects.index') }}" @class(['side-link', 'active' => request()->routeIs('projects.*')])>
                <i class="bi bi-kanban"></i><span>{{ __('app.projects') }}</span>
            </a>
            <a href="{{ route('sprints.index') }}" @class(['side-link', 'active' => request()->routeIs('sprints.*')])>
                <i class="bi bi-lightning-charge"></i><span>{{ __('app.sprints') }}</span>
            </a>
            @if ($user->isDeveloper())
                <a href="{{ route('customers.index') }}" @class(['side-link', 'active' => request()->routeIs('customers.*')])>
                    <i class="bi bi-people"></i><span>{{ __('app.customers') }}</span>
                </a>
            @endif
            @if ($user->isAdmin())
                <a href="{{ route('admin.users.index') }}" @class(['side-link', 'active' => request()->routeIs('admin.users.*')])>
                    <i class="bi bi-person-gear"></i><span>{{ __('app.users') }}</span>
                </a>
            @endif
        @elseif ($projectContext->current())
            <div class="nav-section">{{ __('app.project') }}</div>
            <a href="{{ route('projects.show', $projectContext->current()) }}" @class(['side-link', 'active' => request()->routeIs('projects.*')])>
                <i class="bi bi-kanban"></i><span class="text-truncate">{{ $projectContext->current()->name }}</span>
            </a>
        @endif

        <div class="nav-section">{{ __('app.profile') }}</div>
        <a href="{{ route('profile.edit') }}" @class(['side-link', 'active' => request()->routeIs('profile.*')])>
            <i class="bi bi-person-circle"></i><span>{{ __('app.profile') }}</span>
        </a>
    </nav>

    <div class="sidebar-footer">© {{ date('Y') }} {{ __('app.name') }}</div>
</aside>
