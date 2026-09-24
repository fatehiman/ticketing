<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Profile page for every role. Language, calendar, password and avatar can
 * always be changed. Name, email and mobile are editable only by admins.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'apiTokens' => $user->isDeveloper() ? ApiToken::valid()->where('user_id', $user->id)->with('project')->latest('id')->get() : collect(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $rules = [
            'locale' => ['required', Rule::in(SetLocale::LOCALES)],
            'calendar' => ['required', Rule::in(['jalali', 'gregorian'])],
        ];
        if ($user->isAdmin()) {
            $rules += [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'mobile' => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            ];
        }

        $user->fill($request->validate($rules))->save();
        $request->session()->put('locale', $user->locale);

        return back()->with('success', __('app.saved'));
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', __('users.password_changed'));
    }

    public function avatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        return back()->with('success', __('app.saved'));
    }

    /** Stop a bot at once: delete its API token. */
    public function revokeToken(Request $request, ApiToken $apiToken)
    {
        abort_unless($apiToken->user_id === $request->user()->id, 403);
        $apiToken->delete();

        return back()->with('success', __('api.revoked'));
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('success', __('app.saved'));
    }
}
