{{-- Rename / reorder / delete a custom ticket menu. Filled by JS (initMenuEdit). --}}
<div class="modal fade" id="menuEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square text-brand"></i> {{ __('tickets.menu.edit') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <form method="POST" id="menu-edit-form">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">{{ __('tickets.menu.name') }}</label>
                        <input type="text" name="name" class="form-control" maxlength="60" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('tickets.menu.order') }}</label>
                        <select name="sort_order" class="form-select">
                            @for ($i = 1; $i <= 30; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                        <div class="form-text">{{ __('tickets.menu.order_hint') }}</div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="submit" form="menu-delete-form" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> {{ __('app.delete') }}
                    </button>
                    <div>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </div>
            </form>
            <form method="POST" id="menu-delete-form" data-confirm="{{ __('tickets.menu.confirm_delete') }}">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</div>
