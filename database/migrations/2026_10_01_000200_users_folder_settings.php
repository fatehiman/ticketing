<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Order and hidden built-in folders under "Tickets": {"order": [keys], "hidden": [keys]}. Null = default. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('folder_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('folder_settings');
        });
    }
};
