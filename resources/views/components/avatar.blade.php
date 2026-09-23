@props(['user', 'size' => null])
@php($style = $size ? "--s: {$size}px" : null)
@if ($user?->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => 'avatar']) }} @if ($style) style="{{ $style }}" @endif>
@else
    <span {{ $attributes->merge(['class' => 'avatar']) }} @if ($style) style="{{ $style }}" @endif title="{{ $user?->name }}">{{ $user?->initials ?: '?' }}</span>
@endif
