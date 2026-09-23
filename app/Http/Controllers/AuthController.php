<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    /** Sign in with email or mobile number. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        $login = Dates::latinDigits(trim($data['login']));
        $field = str_contains($login, '@') ? 'email' : 'mobile';
        $user = User::where($field, $login)->first();

        if (! $user || ! Auth::attempt([$field => $login, 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['login' => __('auth.inactive')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
