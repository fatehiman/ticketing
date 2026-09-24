<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bot can ask for a token that is always linked to one project (POST /api/auth/start with
 * "require_project": true). Then the approval page does not offer "No fixed project".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_auth_requests', function (Blueprint $table) {
            $table->boolean('require_project')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('api_auth_requests', function (Blueprint $table) {
            $table->dropColumn('require_project');
        });
    }
};
