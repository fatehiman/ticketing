<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Full access API key of a staff user (all their projects, project sent in the payload). One key per user; rotating replaces it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('full_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('key_hash', 64)->unique();
            $table->string('key_hint', 8);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('full_api_keys');
    }
};
