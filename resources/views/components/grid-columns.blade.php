{{-- Column chooser for a grid. The choice is saved on the server (GridPreferenceController). --}}
@props(['grid'])
<div class="dropdown col-chooser" data-grid="{{ $grid->key }}" data-url="{{ route('grid.save') }}">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi bi-layout-three-columns"></i> {{ __('app.columns') }}
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow border-0 p-2">
        @foreach ($grid->columns as $key => $label)
            <label class="form-check d-flex align-items-center gap-2 py-1 mb-0">
                <input class="form-check-input mt-0" type="checkbox" data-col="{{ $key }}"
                       @checked($grid->visible($key)) @disabled($grid->isLocked($key))>
                <span class="form-check-label small">{{ $label }}</span>
            </label>
        @endforeach
    </div>
</div>
