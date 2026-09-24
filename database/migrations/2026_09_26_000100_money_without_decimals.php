<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Money is always a whole number (all currencies): 7,000,000 — never 7,000,000.00. */
return new class extends Migration
{
    private const COLUMNS = ['projects' => ['budget'], 'tickets' => ['estimated_cost', 'cost']];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->whereNotNull($column)->update([$column => DB::raw("ROUND({$column}, 0)")]);
            }
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->unsignedBigInteger($column)->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->decimal($column, 18, 2)->nullable()->change();
                }
            });
        }
    }
};
