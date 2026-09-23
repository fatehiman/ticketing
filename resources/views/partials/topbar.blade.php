@php($user = auth()->user())
<header class="topbar">
    <button class="top-icon-btn d-lg-none" type="button" data-toggle-sidebar aria-label="{{ __('app.open_menu') }}">
        <i class="bi bi-list fs-5"></i>
    </button>

    @if ($projectContext->showSwitcher())
        <form method="POST" action="{{ route('project.switch') }}" class="project-switcher flex-grow-1 flex-md-grow-0">
            @csrf
            <select name="project_id" class="form-select" onchange="this.form.submit()" aria-label="{{ __('app.project') }}">
                @foreach ($projectContext->options() as $option)
                    <option value="{{ $option->id }}" @selected($projectContext->id() === $option->id)>{{ $option->name }}</option>
                @endforeach
                @if ($projectContext->allowAll())
                    <option value="all" @selected($projectContext->id() === null)>— {{ __('app.all_projects') }} —</option>
                @endif
            </select>
        </form>
    @elseif ($projectContext->current())
        <span class="fw-semibold text-truncate"><i class="bi bi-kanban text-brand"></i> {{ $projectContext->current()->name }}</span>
    @endif

    <div class="ms-auto d-flex align-items-center gap-2">
        <a href="{{ route('tickets.create') }}" class="btn btn-primary d-none d-sm-inline-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i> {{ __('app.new_ticket') }}
        </a>

        @php($other = app()->getLocale() === 'fa' ? 'en' : 'fa')
        <a href="{{ route('locale', $other) }}" class="top-icon-btn" title="{{ __('app.language') }}" data-bs-toggle="tooltip">
            <span class="fw-bold small">{{ $other === 'en' ? 'EN' : 'فا' }}</span>
        </a>

        <div class="dropdown">
            <button class="btn p-0 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                <x-avatar :user="$user" />
                <span class="d-none d-md-block text-start lh-sm">
                    <span class="d-block fw-semibold small">{{ $user->name }}</span>
                    <span class="d-block text-muted" style="font-size:.72rem">{{ $user->role->label() }}</span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-circle me-2"></i>{{ __('app.profile') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('app.logout') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
