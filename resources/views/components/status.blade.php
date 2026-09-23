@props(['status'])
<span class="status-chip" style="--c: {{ $status->color() }}"><i class="bi {{ $status->icon() }}"></i>{{ $status->label() }}</span>
