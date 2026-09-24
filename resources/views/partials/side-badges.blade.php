{{-- Folder badges: total count, and (red) how many of them wait for the user's reply. --}}
<span class="side-badges">
    @if ($item['awaiting'] > 0)
        <span class="side-badge side-badge-alert" title="{{ __('tickets.menu.awaiting_badge') }}">{{ $item['awaiting'] }}</span>
    @endif
    <span @class(['side-badge', 'side-badge-alert' => $item['key'] === 'awaiting' && $item['count'] > 0])>{{ $item['count'] }}</span>
</span>
