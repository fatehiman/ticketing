{{--
    A listbox with check marks: pick many values from a dropdown (name="project[]" …).
    Nothing ticked = no filter (the placeholder is shown). A search box is shown when the list is long.
    app.js (initMultiSelects) updates the button text and filters the list while typing.
--}}
@props(['name', 'options' => [], 'selected' => [], 'placeholder' => '', 'icon' => null])
@php
    $selected = array_map('strval', (array) $selected);
    $picked = collect($options)->filter(fn ($label, $value) => in_array((string) $value, $selected, true))->values();
    $text = match (true) {
        $picked->isEmpty() => $placeholder,
        $picked->count() <= 2 => $picked->implode(__('app.list_sep')),
        default => __('app.n_selected', ['count' => $picked->count()]),
    };
@endphp
<div class="dropdown multi-select" data-multi-select data-placeholder="{{ $placeholder }}" data-many="{{ __('app.n_selected', ['count' => '#']) }}" data-sep="{{ __('app.list_sep') }}">
    <button class="form-select text-start text-truncate" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        @if ($icon)<i class="bi {{ $icon }}"></i>@endif
        <span data-ms-label @class(['text-muted' => $picked->isEmpty()])>{{ $text }}</span>
    </button>
    <div class="dropdown-menu shadow border-0 p-2 w-100">
        @if (count($options) > 8)
            <input type="search" class="form-control form-control-sm mb-2" data-ms-search placeholder="{{ __('app.search') }}…" aria-label="{{ __('app.search') }}">
        @endif
        <div class="ms-list">
            @foreach ($options as $value => $label)
                <label class="form-check d-flex align-items-center gap-2 py-1 mb-0" data-ms-item>
                    <input class="form-check-input mt-0" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(in_array((string) $value, $selected, true))>
                    <span class="form-check-label">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <div class="border-top mt-1 pt-1 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-link text-decoration-none px-1" data-ms-all>{{ __('app.select_all') }}</button>
            <button type="button" class="btn btn-sm btn-link text-decoration-none px-1" data-ms-none>{{ __('app.clear') }}</button>
        </div>
    </div>
</div>
