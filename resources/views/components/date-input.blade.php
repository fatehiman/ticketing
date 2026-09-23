{{-- A date field in the user's calendar: Jalali picker or native Gregorian date input. --}}
@props(['name', 'value' => null])
@php
    $value = old($name, $value instanceof \Carbon\CarbonInterface ? \App\Support\Dates::input($value) : $value);
    $jalali = \App\Support\Dates::isJalali();
@endphp
@if ($jalali)
    <input type="text" name="{{ $name }}" value="{{ $value }}" data-jdp autocomplete="off" placeholder="1405/01/01"
        {{ $attributes->merge(['class' => 'form-control ltr-input'.($errors->has($name) ? ' is-invalid' : '')]) }}>
@else
    <input type="date" name="{{ $name }}" value="{{ $value }}"
        {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>
@endif
