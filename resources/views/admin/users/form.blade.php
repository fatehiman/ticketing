@extends('layouts.app')
@section('title', $user->exists ? __('users.edit') : __('users.new'))

@section('content')
    <div class="page-head">
        <h1><i class="bi bi-person-gear text-brand"></i> {{ $user->exists ? __('users.edit').': '.$user->name : __('users.new') }}</h1>
        <a href="{{ route('admin.users.index') }}" data-return-link class="btn btn-light">{{ __('app.back') }}</a>
    </div>

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        @include('partials.user-fields', ['subject' => $user, 'showRole' => true, 'projectsHint' => ''])
        <div class="mt-3"><button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ __('app.save') }}</button></div>
    </form>
@endsection

@push('scripts')
    <script>
        // Admins see every project, so the project list is hidden for them.
        (() => {
            const card = document.getElementById('projects-card');
            const sync = () => {
                const role = document.querySelector('#role-picker input:checked')?.value;
                card.classList.toggle('d-none', role === 'admin');
            };
            document.querySelectorAll('#role-picker input').forEach((r) => r.addEventListener('change', sync));
            sync();
        })();
    </script>
@endpush
