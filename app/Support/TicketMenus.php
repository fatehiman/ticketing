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
            'custom' => false,
            'active' => false,
        ];
    }

    /** Status-only folders share one grouped query; the others get their own count. */
    private function fillCounts(array &$items): void
    {
        $byStatus = TicketFilter::query([], $this->user, $this->context)
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();

        foreach ($items as &$item) {
            $f = $item['filters'];
            if ($f === []) {
                $item['count'] = array_sum($byStatus);
            } elseif (array_keys($f) === ['status']) {
                $item['count'] = array_sum(array_intersect_key($byStatus, array_flip($f['status'])));
            } else {
                $item['count'] = TicketFilter::query($f, $this->user, $this->context)->count();
            }
        }
    }
}
