<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bank details a developer shows to customers for card-to-card / bank payments.
        Schema::table('users', function (Blueprint $table) {
            $table->string('card_number', 30)->nullable()->after('mobile');
            $table->string('iban', 40)->nullable()->after('card_number');
            $table->string('account_holder', 150)->nullable()->after('iban');
        });

        // Bills (invoices). Each item is a ticket: picked from done tickets, or a manual item that
        // the bill page creates as a done ticket. Title and amount are copied at issue time.
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('number')->nullable()->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('customer_id')->constrained('users');
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->string('description', 1000)->nullable();
            $table->unsignedBigInteger('total')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'issued_on']);
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->unsignedBigInteger('amount');
            $table->boolean('is_manual')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Payments become vouchers: a customer registers a bank payment (pending), a developer
        // accepts or declines it. Only accepted payments count on the transactions page.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 10)->default('accepted')->after('amount'); // pending | accepted | declined
            $table->string('paid_time', 5)->nullable()->after('paid_on');        // HH:MM
            $table->string('reference_no', 60)->nullable()->after('paid_time');  // bank tracking number
            $table->foreignId('bill_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->index(['status', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'customer_id']);
            $table->dropConstrainedForeignId('bill_id');
            $table->dropColumn(['status', 'paid_time', 'reference_no']);
        });
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['card_number', 'iban', 'account_holder']);
        });
    }
};
