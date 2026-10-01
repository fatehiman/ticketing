<?php

namespace App\Http\Controllers;

use App\Support\TicketMenus;
use Illuminate\Http\Request;

/** Order and show / hide of the built-in folders under "Tickets". Custom folders are not part of it. */
class FolderSettingsController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string', 'max:50', 'alpha_dash'],
            'visible' => ['nullable', 'array'],
            'visible.*' => ['string', 'max:50', 'alpha_dash'],
        ]);

        // Only keys of real built-in folders are kept.
        $keys = TicketMenus::builtinKeys();
        $order = array_values(array_unique(array_intersect($data['order'], $keys)));
        $hidden = array_values(array_diff($order, $data['visible'] ?? []));

        $request->user()->update(['folder_settings' => ['order' => $order, 'hidden' => $hidden]]);

        return back()->with('success', __('app.saved'));
    }

    public function destroy(Request $request)
    {
        $request->user()->update(['folder_settings' => null]);

        return back()->with('success', __('tickets.folders.reset_done'));
    }
}
