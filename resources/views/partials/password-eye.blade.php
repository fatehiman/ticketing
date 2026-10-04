{{-- Show / hide password button. Closed eye = hidden. Open eye blinks, looks around and follows the mouse (initPasswordEye). --}}
<button type="button" class="input-group-text pw-eye" data-eye-for="{{ $for }}"
        aria-pressed="false" aria-label="{{ __('auth.show_password') }}" title="{{ __('auth.show_password') }}"
        data-label-show="{{ __('auth.show_password') }}" data-label-hide="{{ __('auth.hide_password') }}">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
        {{-- closed: a lid with lashes --}}
        <g class="eye-closed" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <path d="M3 11c2.5 3.2 5.5 4.8 9 4.8s6.5-1.6 9-4.8"/>
            <path d="M6.2 14.2 4.8 16.3M12 15.8V18.3M17.8 14.2l1.4 2.1"/>
        </g>
        {{-- open: the white, then the iris and pupil that move --}}
        <g class="eye-open">
            <g class="eye-ball">
                <path d="M2.5 12C5 7.6 8.2 5.5 12 5.5s7 2.1 9.5 6.5c-2.5 4.4-5.7 6.5-9.5 6.5S5 16.4 2.5 12Z" fill="#fff" stroke="currentColor" stroke-width="1.6"/>
                <g class="eye-pupil">
                    <circle cx="12" cy="12" r="3.6" fill="currentColor"/>
                    <circle cx="12" cy="12" r="1.6" fill="#111"/>
                    <circle cx="13.2" cy="10.8" r="0.8" fill="#fff"/>
                </g>
            </g>
        </g>
    </svg>
</button>
