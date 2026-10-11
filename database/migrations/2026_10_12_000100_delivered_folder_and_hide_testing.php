<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One time, for users who saved their own folder settings (others use the defaults, which
     * already have it): the new Delivered folder comes right after Done and is visible,
     * and the Testing folder is hidden. Users can show it again with the pencil.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('folder_settings')->orderBy('id')->each(function ($user) {
            $settings = json_decode($user->folder_settings, true);
            if (! is_array($settings)) {
                return;
            }
            $order = array_values(array_diff($settings['order'] ?? [], ['status_delivered']));
            $at = array_search('status_done', $order, true);
            array_splice($order, $at === false ? count($order) : $at + 1, 0, ['status_delivered']);

            $hidden = array_values(array_diff($settings['hidden'] ?? [], ['status_delivered', 'status_testing']));
            $hidden[] = 'status_testing';

            DB::table('users')->where('id', $user->id)
                ->update(['folder_settings' => json_encode(['order' => $order, 'hidden' => $hidden])]);
        });
    }

    public function down(): void
    {
        // The old choices are not kept.
    }
};
