<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One time: everyone starts from the new defaults (the owner's choice, now in
     * TicketMenus::DEFAULT_ORDER / DEFAULT_HIDDEN and TicketController::FOLDER_COLUMNS):
     * built-in folder order / show-hide, and the columns of the tickets grids.
     * Custom folders and their column choices are kept.
     */
    public function up(): void
    {
        DB::table('users')->update(['folder_settings' => null]);

        DB::table('grid_preferences')
            ->where(fn ($q) => $q->where('grid_key', 'tickets')->orWhere('grid_key', 'like', 'tickets-%'))
            ->where('grid_key', 'not like', 'tickets-custom%')
            ->delete();
    }

    public function down(): void
    {
        // Old choices are not kept.
    }
};
