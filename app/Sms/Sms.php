<?php

namespace App\Sms;

use App\Jobs\SendSms;
use App\Models\SmsMessage;
use App\Support\Dates;

/**
 * Put an SMS in the outbox. The queue worker sends it (App\Jobs\SendSms), so pages never wait
 * for the gateway. Failed sends are retried after 1, 5, 10 and 30 minutes; OTP codes only once
 * after 1 minute.
 */
class Sms
{
    /**
     * @param  string  $template  key of config('sms.templates'), also saved as the purpose
     * @param  array  $params  values for [param1], [param2], … in order
     * @param  string  $method  sms, or ivr for a voice call (uses the IVR template)
     */
    public static function send(?string $mobile, string $template, array $params = [], ?string $code = null, string $method = 'sms'): ?SmsMessage
    {
        $mobile = self::normalize($mobile);
        if ($mobile === null) {
            return null;
        }

        $sms = SmsMessage::create([
            'mobile' => $mobile,
            'method' => $method,
            'template_id' => $method === 'ivr' ? (int) config('services.msgway.ivr_template') : (int) config("sms.templates.$template"),
            'params' => array_values($params),
            'code' => $code,
            'purpose' => $template,
            'status' => SmsMessage::QUEUED,
        ]);

        SendSms::dispatch($sms->id, $template === 'otp')->afterCommit();

        return $sms;
    }

    /** "09121234567", "۰۹۱۲…", "9121234567", "989121234567" → "+989121234567". Null when it is not a mobile number. */
    public static function normalize(?string $mobile): ?string
    {
        $raw = trim(Dates::latinDigits((string) $mobile));
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return null;
        }
        $local = match (true) {
            str_starts_with($digits, '0098') => substr($digits, 4),
            str_starts_with($digits, '98') && strlen($digits) === 12 => substr($digits, 2),
            str_starts_with($digits, '0') && strlen($digits) === 11 => substr($digits, 1),
            default => $digits,
        };
        if (preg_match('/^9\d{9}$/', $local)) {
            return '+98'.$local;
        }
        // A non-Iranian number written with + or 00.
        if ((str_starts_with($raw, '+') || str_starts_with($digits, '00')) && strlen(ltrim($digits, '0')) >= 8) {
            return '+'.ltrim($digits, '0');
        }

        return null;
    }
}
