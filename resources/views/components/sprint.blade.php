{{-- A sprint name; green with a dot when today is inside its dates (Sprint::isCurrent()). --}}
@props(['sprint', 'text' => null])
@if ($sprint)
    @php($text ??= $sprint->label())
    @if ($sprint->isCurrent())
        <span {{ $attributes->class('sprint-current') }} title="{{ __('tickets.menu.current_sprint') }}"><i class="bi bi-circle-fill"></i> {{ $text }}</span>
    @else
        <span {{ $attributes }}>{{ $text }}</span>
    @endif
@else
    {{ $slot->isEmpty() ? '' : $slot }}
@endif
