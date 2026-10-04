{{-- Header of the guest cards (login, forgot password): logo, name, tagline and language switch. --}}
<div class="d-flex justify-content-between align-items-start mb-4">
    <div class="d-flex align-items-center gap-2">
        <span class="avatar" style="--s:48px;border-radius:14px"><i class="bi bi-ticket-perforated fs-4"></i></span>
        <div>
            <div class="fw-bold fs-5">{{ __('app.name') }}</div>
            <div class="text-muted small">{{ __('app.tagline') }}</div>
        </div>
    </div>
    <div class="btn-group btn-group-sm">
        <a href="{{ route('locale', 'fa') }}" @class(['btn', 'btn-primary' => app()->getLocale() === 'fa', 'btn-outline-secondary' => app()->getLocale() !== 'fa'])>فا</a>
        <a href="{{ route('locale', 'en') }}" @class(['btn', 'btn-primary' => app()->getLocale() === 'en', 'btn-outline-secondary' => app()->getLocale() !== 'en'])>EN</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success small"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
@endif
