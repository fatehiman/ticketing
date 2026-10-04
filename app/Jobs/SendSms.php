<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Sms\MsgwayClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Sends one row of the SMS outbox. On a failure that may pass (timeout, gateway down) the job
 * is tried again after 1, 5, 10 and 30 minutes, then the row is marked failed.
 * OTP codes are tried again only once, after 1 minute (they expire after 2 minutes).
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var int[] seconds to wait before each retry */
    public array $backoff;

    public function __construct(public int $smsId, bool $otp = false)
    {
        $this->backoff = config($otp ? 'sms.otp_backoff' : 'sms.backoff');
        $this->tries = count($this->backoff) + 1;
    }

    public function handle(MsgwayClient $client): void
    {
        $sms = SmsMessage::find($this->smsId);
        if (! $sms || $sms->status !== SmsMessage::QUEUED) {
            return;
        }

        $sms->increment('attempts');
        $result = $client->send($sms->mobile, $sms->template_id, $sms->params ?? [], $sms->code, $sms->method);

        if ($result['ok']) {
            $sms->update(['status' => SmsMessage::SENT, 'reference_id' => $result['reference_id'] ?? null, 'sent_at' => now(), 'last_error' => null]);

            return;
        }

        $sms->update(['last_error' => mb_substr($result['error'] ?? 'unknown error', 0, 500)]);
        $error = new RuntimeException('SMS '.$sms->id.' not sent: '.($result['error'] ?? 'unknown error'));
        if (! ($result['retryable'] ?? true)) {
            $this->fail($error); // a wrong request stays wrong: do not pay for more tries

            return;
        }

        throw $error; // the queue tries again after the next backoff
    }

    public function failed(?Throwable $e): void
    {
        SmsMessage::whereKey($this->smsId)->where('status', SmsMessage::QUEUED)->update(['status' => SmsMessage::FAILED]);
    }
}
