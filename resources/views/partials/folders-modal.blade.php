{{-- Order and show / hide of the built-in folders (the pencil next to "Tickets"). Moving rows: initFolders. --}}
@php($folders = collect($ticketMenus ?? [])->where('custom', false))
<div class="modal fade" id="foldersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-folder2-open text-brand"></i> {{ __('tickets.folders.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <form method="POST" action="{{ route('folder-settings.update') }}" id="folders-form" class="folders-form">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-text mt-0 mb-2">{{ __('tickets.folders.hint') }}</div>
                    <ul class="list-group folder-list">
                        @foreach ($folders as $item)
                            <li class="list-group-item d-flex align-items-center gap-2" draggable="true">
                                <input type="hidden" name="order[]" value="{{ $item['key'] }}">
                                <i class="bi bi-grip-vertical text-muted folder-grip"></i>
                                @if ($item['color'])
                                    <span class="dot" style="--c: {{ $item['color'] }}"></span>
                                @else
                                    <i class="bi {{ $item['icon'] }}"></i>
                                @endif
                                <span class="flex-grow-1 text-truncate">{{ $item['name'] }}</span>
                                <button type="button" class="btn btn-sm btn-light folder-up" title="{{ __('tickets.folders.up') }}"><i class="bi bi-arrow-up"></i></button>
                                <button type="button" class="btn btn-sm btn-light folder-down" title="{{ __('tickets.folders.down') }}"><i class="bi bi-arrow-down"></i></button>
                                <div class="form-check form-switch m-0 ms-1" title="{{ __('tickets.folders.show') }}">
                                    <input class="form-check-input" type="checkbox" role="switch" name="visible[]"
                                           value="{{ $item['key'] }}" @checked(! $item['hidden'])>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="submit" form="folders-reset-form" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> {{ __('tickets.folders.reset') }}
                    </button>
                    <div>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </div>
            </form>
            <form method="POST" action="{{ route('folder-settings.reset') }}" id="folders-reset-form" data-confirm="{{ __('tickets.folders.confirm_reset') }}">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</div>
