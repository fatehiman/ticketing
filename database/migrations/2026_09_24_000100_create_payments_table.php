<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer payments. Ticket costs are NOT copied here: the transactions page reads them
        // live from done tickets, so a ticket that changes never leaves a stale row behind.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'paid_on']);
            $table->index(['project_id', 'paid_on']);
        });

        // Tickets used by the transactions page (done + cost + due date).
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['status', 'due_date']);
        });

        // New default columns for the tickets grid: everyone starts again from the defaults once.
        DB::table('grid_preferences')->where('grid_key', 'tickets')->delete();
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_date']);
        });
        Schema::dropIfExists('payments');
    }
};
