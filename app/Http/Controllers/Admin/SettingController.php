<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Uploads;
use Illuminate\Http\Request;

/** Admin: site settings. */
class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'attachmentMaxMb' => (int) Setting::get('attachment_max_mb', Uploads::DEFAULT_MB),
            'serverMaxMb' => Uploads::serverMaxMb(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'attachment_max_mb' => ['required', 'integer', 'min:1', 'max:'.Uploads::serverMaxMb()],
        ], [], ['attachment_max_mb' => __('settings.attachment_max_mb')]);

        Setting::put('attachment_max_mb', $data['attachment_max_mb']);

        return redirect()->route('admin.settings.edit')->with('success', __('app.saved'));
    }
}
