<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Followups (the conversation of a ticket) and ratings.
 *
 * - ticket_followups: replies from staff or customers, with HTML body and attachments.
 * - tickets.awaiting_reply: which side must answer now ('staff' | 'customer' | null).
 * - ticket_comments becomes the customer's rating of a closed ticket (1-5 stars, one per ticket).
 *   Old free-text comments are moved to followups, so nothing is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->longText('body')->nullable();
            $table->boolean('awaits_reply')->default(true);
            $table->timestamps();
            $table->index(['ticket_id', 'id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('awaiting_reply', 10)->nullable()->after('status');
            $table->timestamp('awaiting_since')->nullable()->after('awaiting_reply');
            $table->index('awaiting_reply');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('followup_id')->nullable()->after('ticket_id')->constrained('ticket_followups')->cascadeOnDelete();
        });

        // Move the old comments to followups (plain text → simple HTML).
        foreach (DB::table('ticket_comments')->orderBy('id')->get() as $c) {
            DB::table('ticket_followups')->insert([
                'ticket_id' => $c->ticket_id,
                'user_id' => $c->user_id,
                'body' => '<p>'.nl2br(e($c->body), false).'</p>',
                'awaits_reply' => false,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
            ]);
        }
        DB::table('ticket_comments')->delete();

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->default(5)->after('user_id');
            $table->text('body')->nullable()->change();
            $table->unique('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropUnique(['ticket_id']);
            $table->dropColumn('rating');
        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('followup_id');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['awaiting_reply']);
            $table->dropColumn(['awaiting_reply', 'awaiting_since']);
        });
        Schema::dropIfExists('ticket_followups');
    }
};
