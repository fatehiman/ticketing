<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Shared validation rules for the user forms (admin users + developer customers). */
class UserRules
{
    public static function rules(?int $ignoreId = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($ignoreId)],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/', Rule::unique('users')->ignore($ignoreId)],
            'password' => [$ignoreId ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'locale' => ['required', Rule::in(SetLocale::LOCALES)],
            'calendar' => ['required', Rule::in(['jalali', 'gregorian'])],
            'is_active' => ['boolean'],
            'projects' => ['nullable', 'array'],
            'projects.*' => ['integer'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /** Latin digits for the mobile number before validation. */
    public static function prepare(\Illuminate\Http\Request $request): void
    {
        $request->merge([
            'mobile' => Dates::latinDigits((string) $request->input('mobile')) ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
