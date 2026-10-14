<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Read the text of every SMS template (config/sms.php + the IVR template) from msgway into message_templates.
Artisan::command('sms:templates', function (\App\Sms\MsgwayClient $client) {
    $ids = array_unique([...array_values(config('sms.templates')), (int) config('services.msgway.ivr_template')]);
    foreach ($ids as $id) {
        $body = $client->template((int) $id);
        if ($body === null) {
            $this->warn("Template $id: not read (no API key, or msgway did not answer).");

            continue;
        }
        \App\Models\MessageTemplate::updateOrCreate(
            ['provider' => \App\Models\MessageTemplate::MSGWAY, 'template_id' => $id],
            ['body' => $body, 'synced_at' => now()],
        );
        $this->info("Template $id: ".str_replace(["\r", "\n"], ['', ' | '], $body));
    }
})->purpose('Read the SMS template texts from msgway');
