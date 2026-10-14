{{-- session('success') / session('warning') are toasts now (App\Support\Toast::forPage()); form errors stay here. --}}
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
