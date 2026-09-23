<?php

namespace App\Http\Controllers;

use App\Models\TicketMenu;
use App\Support\TicketFilter;
use Illuminate\Http\Request;

/** Custom ticket folders ("cartables"): a name + a saved filter set. */
class TicketMenuController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'filters' => ['nullable', 'array'],
        ]);
        $user = $request->user();

        $menu = $user->ticketMenus()->create([
            'name' => $data['name'],
            'filters' => TicketFilter::normalize($data['filters'] ?? []),
            'sort_order' => ((int) $user->ticketMenus()->max('sort_order')) + 1,
        ]);

        $url = route('tickets.index', $menu->filters);
        session()->flash('success', __('tickets.menu.created', ['name' => $menu->name]));

        return $request->expectsJson() ? response()->json(['url' => $url]) : redirect()->to($url);
    }

    public function update(Request $request, TicketMenu $ticketMenu)
    {
        abort_unless($ticketMenu->user_id === $request->user()->id, 403);
        $ticketMenu->update($request->validate([
            'name' => ['required', 'string', 'max:60'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]));

        return back()->with('success', __('app.saved'));
    }

    public function destroy(Request $request, TicketMenu $ticketMenu)
    {
        abort_unless($ticketMenu->user_id === $request->user()->id, 403);
        $ticketMenu->delete();

        return redirect()->route('tickets.index')->with('success', __('app.deleted'));
    }
}
