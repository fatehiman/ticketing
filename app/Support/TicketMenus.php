<?php

namespace App\Support;

use App\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds the items under the "Tickets" menu: built-in folders + the user's
 * custom folders. Each item is only a filter set plus a badge count.
 */
class TicketMenus
{
    public function __construct(private User $user, private ProjectContext $context) {}

    public function items(?array $currentFilters = null): array
    {
        $items = [];

        $items[] = $this->item('open', __('tickets.menu.open'), 'bi-inbox', null, [
            'status' => array_map(fn ($s) => $s->value, array_filter(TicketStatus::cases(), fn ($s) => ! $s->isClosed())),
        ]);
        $items[] = $this->item('awaiting', __('tickets.menu.awaiting'), 'bi-reply', null, ['awaiting' => 'me']);
        $items[] = $this->item('all', __('tickets.menu.all'), 'bi-collection', null, []);

        foreach (TicketStatus::cases() as $status) {
            // Pending review is only meaningful for staff and customers; everyone sees it.
            $items[] = $this->item('status_'.$status->value, $status->label(), $status->icon(), $status->color(), ['status' => [$status->value]]);
        }

        if ($this->user->isStaff()) {
            $items[] = $this->item('mine', __('tickets.menu.assigned_to_me'), 'bi-person-check', null, ['assignee_id' => 'me']);
            $items[] = $this->item('unassigned', __('tickets.menu.unassigned'), 'bi-person-dash', null, ['assignee_id' => 'none']);
        }
        $items[] = $this->item('reported', __('tickets.menu.reported_by_me'), 'bi-pencil-square', null, ['reporter_id' => 'me']);

        foreach ($this->user->ticketMenus as $menu) {
            $item = $this->item('custom_'.$menu->id, $menu->name, 'bi-folder2', null, $menu->filters ?? []);
            $item['custom'] = true;
            $item['id'] = $menu->id;
            $item['sort_order'] = $menu->sort_order;
            $items[] = $item;
        }

        $this->fillCounts($items);

        if ($currentFilters !== null) {
            $current = TicketFilter::normalize($currentFilters);
            foreach ($items as &$item) {
                $item['active'] = $item['filters'] === $current;
            }
        }

        return $items;
    }

    private function item(string $key, string $name, string $icon, ?string $color, array $filters): array
    {
        $filters = TicketFilter::normalize($filters);

        return [
            'key' => $key,
            'name' => $name,
            'icon' => $icon,
            'color' => $color,
            'filters' => $filters,
            'url' => route('tickets.index', $filters),
            'count' => 0,
            'awaiting' => 0, // tickets in this folder that wait for the user's reply (red badge)
            'custom' => false,
            'active' => false,
        ];
    }

    /**
     * Status-only folders share one grouped query; the others get their own count.
     * Each folder also gets the number of its tickets that wait for the user's reply.
     */
    private function fillCounts(array &$items): void
    {
        $side = $this->user->replySide();
        $rows = TicketFilter::query([], $this->user, $this->context)
            ->select('status', DB::raw('count(*) as c'), DB::raw("sum(case when awaiting_reply = '{$side}' then 1 else 0 end) as w"))
            ->groupBy('status')
            ->get();
        $byStatus = $rows->mapWithKeys(fn ($r) => [$r->getRawOriginal('status') => (int) $r->c])->all();
        $waiting = $rows->mapWithKeys(fn ($r) => [$r->getRawOriginal('status') => (int) $r->w])->all();

        foreach ($items as &$item) {
            $f = $item['filters'];
            if ($f === []) {
                $item['count'] = array_sum($byStatus);
                $item['awaiting'] = array_sum($waiting);
            } elseif (array_keys($f) === ['status']) {
                $keys = array_flip($f['status']);
                $item['count'] = array_sum(array_intersect_key($byStatus, $keys));
                $item['awaiting'] = array_sum(array_intersect_key($waiting, $keys));
            } elseif ($f === ['awaiting' => 'me']) {
                $item['count'] = array_sum($waiting); // every ticket here waits, no second badge needed
            } else {
                $query = TicketFilter::query($f, $this->user, $this->context);
                $item['count'] = (clone $query)->count();
                $item['awaiting'] = $query->where('awaiting_reply', $side)->count();
            }
        }
    }
}
