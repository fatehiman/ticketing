<?php

namespace App\Http\Controllers;

use App\Support\ReturnTo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** After saving a form: back to the list page the user came from ("_back"), else to $fallback. */
    protected function redirectBack(Request $request, string $fallback): RedirectResponse
    {
        return redirect()->to(ReturnTo::from($request) ?? $fallback);
    }
}
