<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The new "Days" column of the sprints grid is visible also for users who already saved their columns. */
    public function up(): void
    {
        DB::table('grid_preferences')->where('grid_key', 'sprints')->get()->each(function ($row) {
            $columns = json_decode($row->columns, true) ?: [];
            if (! in_array('days', $columns, true)) {
                $columns[] = 'days';
                DB::table('grid_preferences')->where('id', $row->id)->update(['columns' => json_encode($columns)]);
            }
        });
    }

    public function down(): void {}
};
