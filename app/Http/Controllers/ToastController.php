<?php

namespace App\Http\Controllers;

use App\Support\Toast;
use Illuminate\Http\Request;

/** Polling of the toasts that wait for the user (every 15 seconds from an open page, see initToasts() in app.js). */
class ToastController extends Controller
{
    public function __invoke(Request $request)
    {
        return response()->json(['toasts' => Toast::pull($request->user())]);
    }
}
