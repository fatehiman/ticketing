<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Text of the SMS templates of a provider (msgway sends only a template ID and params).
        // Used to show the SMS text in a toast. `php artisan sms:templates` reads them again from msgway.
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);                     // msgway
            $table->unsignedInteger('template_id');
            $table->text('body');                               // "تیکت شماره [param1] انجام شد."
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'template_id']);
        });

        // Toasts that wait for a user: shown on the next page or by polling (GET /toasts), then deleted.
        // Older than a few minutes = the user was not online: they are dropped (App\Support\Toast::TTL).
        Schema::create('user_toasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('data');                               // type, title, body, icon, url, link
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'id']);
        });

        // Texts read from msgway on 2026-10-14 (deb10 has no API key, so it could not read them).
        $now = now();
        $templates = [
            2 => '[code]',
            3 => "کد تایید شما: [code]\r\n",
            24561 => "کاربر عزیز،\nپاسخی برای تیکت شماره [param1] شما ثبت شد.\nticketing.kimiasoft.ir\nلغو۱۱",
            24562 => "کاربر عزیز،\nفاکتور جدیدی به شماره [param1] برای شما صادر شد.\nticketing.kimiasoft.ir\nلغو۱۱",
            24564 => "تیکت جدیدی برای پروژه [param1] ثبت شد.\nشماره تیکت: [param2]\nلغو۱۱",
            24610 => "کاربر عزیز،\nتیکت شماره [param1] انجام شد.\nticketing.kimiasoft.ir\nلغو۱۱",
        ];
        DB::table('message_templates')->insert(array_map(fn ($id, $body) => [
            'provider' => 'msgway', 'template_id' => $id, 'body' => $body,
            'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ], array_keys($templates), $templates));
    }

    public function down(): void
    {
        Schema::dropIfExists('user_toasts');
        Schema::dropIfExists('message_templates');
    }
};
