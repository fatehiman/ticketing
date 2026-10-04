<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SMS outbox + log. A row is written first, then the queue worker sends it (with retries),
        // so a slow or broken gateway never slows down a page.
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 20);
            $table->string('method', 10)->default('sms');      // sms | ivr (voice call)
            $table->unsignedInteger('template_id');
            $table->json('params')->nullable();                 // values for [param1], [param2], …
            $table->text('code')->nullable();                   // [code] value, encrypted
            $table->string('purpose', 40);                      // otp, bill_issued, ticket_to_developer, …
            $table->string('status', 10)->default('queued');    // queued | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('reference_id', 64)->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['mobile', 'created_at']);
        });

        // Password recovery codes sent by SMS (or by a voice call on the 3rd request).
        Schema::create('password_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mobile', 20);
            $table->text('code');                               // encrypted: a resend sends the same code
            $table->unsignedTinyInteger('sends')->default(1);
            $table->unsignedTinyInteger('attempts')->default(0); // wrong codes entered
            $table->timestamp('last_sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_otps');
        Schema::dropIfExists('sms_messages');
    }
};
