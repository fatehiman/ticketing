<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\PublicAvatar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait SavesAvatar
{
    /** Handles the "avatar" upload and the "remove_avatar" checkbox of a user form. */
    protected function saveAvatar(Request $request, User $user): void
    {
        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);
        }
    }

    /**
     * After the form is saved: a user with an email and no picture gets a public one (Gravatar, …),
     * at most 10 seconds. Not when the picture was just removed on purpose.
     */
    protected function findPublicAvatar(Request $request, User $user): void
    {
        if (! $request->boolean('remove_avatar')) {
            PublicAvatar::fill($user->fresh());
        }
    }
}
