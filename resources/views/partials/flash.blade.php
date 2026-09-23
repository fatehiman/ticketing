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
