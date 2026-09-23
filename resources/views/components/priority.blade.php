@props(['priority', 'iconOnly' => false])
<span class="prio" style="--c: {{ $priority->color() }}" @if ($iconOnly) title="{{ $priority->label() }}" data-bs-toggle="tooltip" @endif>
    <i class="bi {{ $priority->icon() }}"></i>@unless ($iconOnly){{ $priority->label() }}@endunless
</span>
