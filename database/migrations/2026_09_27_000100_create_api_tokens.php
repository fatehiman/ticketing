<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API for bots (e.g. an AI Telegram bot).
 *
 * - api_auth_requests: one login attempt of a bot. The bot gets a public link (public_id) for the
 *   developer and a private secret for itself. The developer opens the link, signs in and picks a
 *   project; then the bot trades request id + secret for a token (once).
 * - api_tokens: bearer tokens (only the SHA-256 hash is stored), 30 days, optionally linked to one project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('api_auth_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->char('secret_hash', 64);
            $table->string('name', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_token_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_auth_requests');
        Schema::dropIfExists('api_tokens');
    }
};
