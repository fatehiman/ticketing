<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

/** Quick language switch (the theme follows the language: fa → RTL, en → LTR). */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale)
    {
        abort_unless(in_array($locale, SetLocale::LOCALES, true), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
