{{-- Clickable column header that sorts the tickets grid. --}}
@props(['column', 'label', 'sort', 'dir'])
@php
    $active = $sort === $column;
    $next = $active && $dir === 'desc' ? 'asc' : 'desc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]) }}">
    {{ $label }}
    @if ($active)
        <i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill text-brand"></i>
    @endif
</a>
