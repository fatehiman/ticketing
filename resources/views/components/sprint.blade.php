{{-- A sprint name; green when today is inside its dates (Sprint::isCurrent()). --}}
@props(['sprint', 'text' => null])
@if ($sprint)
    @php($text ??= $sprint->label())
    @if ($sprint->isCurrent())
        <span {{ $attributes->class('sprint-current') }} title="{{ __('tickets.menu.current_sprint') }}">{{ $text }}</span>
    @else
        <span {{ $attributes }}>{{ $text }}</span>
    @endif
@else
    {{ $slot->isEmpty() ? '' : $slot }}
@endif
