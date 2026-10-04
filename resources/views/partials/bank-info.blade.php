{{-- Bank details of a developer ($bank): card number, IBAN and account holder, with copy buttons. --}}
<div class="bank-info">
    @if ($bank->card_number)
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="text-muted small">{{ __('users.fields.card_number') }}:</span>
            <b class="ltr-input d-inline-block">{{ trim(chunk_split($bank->card_number, 4, ' ')) }}</b>
            <button type="button" class="btn btn-sm btn-link p-0 d-print-none" data-copy="{{ $bank->card_number }}" title="{{ __('bills.copy') }}"><i class="bi bi-copy"></i></button>
        </div>
    @endif
    @if ($bank->iban)
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="text-muted small">{{ __('users.fields.iban') }}:</span>
            <b class="ltr-input d-inline-block">{{ $bank->iban }}</b>
            <button type="button" class="btn btn-sm btn-link p-0 d-print-none" data-copy="{{ $bank->iban }}" title="{{ __('bills.copy') }}"><i class="bi bi-copy"></i></button>
        </div>
    @endif
    <div class="small">
        <span class="text-muted">{{ __('users.fields.account_holder') }}:</span>
        {{ $bank->account_holder ?: $bank->name }}
    </div>
</div>
