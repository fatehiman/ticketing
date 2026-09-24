@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('app.close') }}"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        <ul class="mb-0 ps-3 d-inline-block align-top">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
@if (session('awaiting_toast'))
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast toast-awaiting border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="10000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-reply-fill me-1"></i>
                    {{ trans_choice('tickets.followup.toast', session('awaiting_toast'), ['count' => session('awaiting_toast')]) }}
                    <a href="{{ route('tickets.index', ['awaiting' => 'me']) }}" class="fw-semibold text-white ms-1">{{ __('tickets.followup.toast_link') }}</a>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('app.close') }}"></button>
            </div>
        </div>
    </div>
@endif
