<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Inline image upload for the rich-text editor (TinyMCE expects {"location": url}). */
class EditorUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']]);

        $path = $request->file('file')->store('editor/'.now()->format('Y/m'), 'public');

        return response()->json(['location' => '/storage/'.$path]);
    }
}
