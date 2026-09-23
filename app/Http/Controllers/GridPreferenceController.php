<?php

namespace App\Http\Controllers;

use App\Models\GridPreference;
use Illuminate\Http\Request;

/** Saves which columns a user wants to see in a grid. */
class GridPreferenceController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'grid' => ['required', 'string', 'max:50', 'alpha_dash'],
            'columns' => ['present', 'array'],
            'columns.*' => ['string', 'max:50', 'alpha_dash'],
        ]);

        GridPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'grid_key' => $data['grid']],
            ['columns' => array_values($data['columns'])],
        );

        return response()->json(['ok' => true]);
    }
}
