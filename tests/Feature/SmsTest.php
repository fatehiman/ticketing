<?php

namespace Tests\Feature;

use App\Jobs\SendSms;
use App\Models\Project;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\TicketService;
use App\Sms\MsgwayClient;
use App\Sms\Sms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.msgway.key' => 'test-key']);
    }

    public function test_mobile_numbers_are_normalized(): void
    {
        foreach (['09121234567', '۰۹۱۲۱۲۳۴۵۶۷', '9121234567', '989121234567', '+98 912 123 4567', '00989121234567'] as $in) {
            $this->assertSame('+989121234567', Sms::normalize($in), $in);
        }
        $this->assertNull(Sms::normalize(''));
        $this->assertNull(Sms::normalize('12345'));
        $this->assertSame('+447700900123', Sms::normalize('+44 7700 900123'));
    }

    public function test_sms_is_sent_by_the_job_with_code_at_top_level(): void
    {
        Http::fake(['api.msgway.com/*' => Http::response(['status' => 'success', 'referenceID' => '777', 'error' => null])]);

        $sms = Sms::send('09121234567', 'otp', [], '482913');

        $this->assertSame(SmsMessage::SENT, $sms->fresh()->status);
        $this->assertSame('777', $sms->fresh()->reference_id);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.msgway.com/send'
            && $r->hasHeader('apiKey', 'test-key')
            && $r['mobile'] === '+989121234567' && $r['templateID'] === 3 && $r['method'] === 'sms'
            && $r['params'] === [] && $r['code'] === '482913');

        // Positional params, no code; a voice call uses the IVR template and a provider.
        Sms::send('09121234567', 'ticket_to_developer', ['Shop', 1047]);
        Http::assertSent(fn (Request $r) => $r['templateID'] === 24564 && $r['params'] === ['Shop', '1047'] && ! isset($r['code']));
        Sms::send('09121234567', 'otp', [], '111222', 'ivr');
        Http::assertSent(fn (Request $r) => $r['method'] === 'ivr' && $r['templateID'] === 2 && $r['provider'] === 1 && $r['code'] === '111222');
    }

    public function test_bad_request_fails_without_retry(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'error' => ['code' => 2001010102, 'message' => 'badRequest', 'traceID' => 'T1']], 400)]);

        $sms = Sms::send('09121234567', 'bill_issued', [1001]);

        $sms->refresh();
        $this->assertSame(SmsMessage::FAILED, $sms->status);
        $this->assertSame(1, $sms->attempts);
        $this->assertStringContainsString('T1', $sms->last_error);
    }

    public function test_gateway_errors_are_retried_with_the_right_waits(): void
    {
        $normal = new SendSms(1);
        $this->assertSame([60, 300, 600, 1800], $normal->backoff);
        $this->assertSame(5, $normal->tries);
        $otp = new SendSms(1, true);
        $this->assertSame([60], $otp->backoff);
        $this->assertSame(2, $otp->tries);

        Queue::fake();
        Http::fake(['*' => Http::response('Bad gateway', 502)]);
        $sms = Sms::send('09121234567', 'otp', [], '123456');
        Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->smsId === $sms->id && $job->tries === 2);

        // A 502 is worth another try: the job throws, so the queue runs it again later.
        $this->expectException(RuntimeException::class);
        try {
            (new SendSms($sms->id, true))->handle(app(MsgwayClient::class));
        } finally {
            $this->assertSame(SmsMessage::QUEUED, $sms->fresh()->status);
            $this->assertSame(1, $sms->fresh()->attempts);
        }
    }

    public function test_ticket_events_put_sms_in_the_outbox(): void
    {
        Queue::fake();
        $dev = User::factory()->developer()->create(['mobile' => '09120000001']);
        $dev2 = User::factory()->developer()->create(['mobile' => null]);
        $customer = User::factory()->customer()->create(['mobile' => '09120000002']);
        $project = Project::create(['code' => 'SH', 'name' => 'Shop']);
        $project->members()->attach([$dev->id, $dev2->id, $customer->id]);
        $service = app(TicketService::class);

        // A customer's new ticket → every developer of the project with a mobile.
        $ticket = $service->create(['project_id' => $project->id, 'type' => 'task', 'priority' => 'medium', 'status' => 'pending_review', 'title' => 'Help'], $customer);
        $this->assertDatabaseHas('sms_messages', ['mobile' => '+989120000001', 'template_id' => 24564, 'purpose' => 'ticket_to_developer']);
        $this->assertSame(['Shop', $ticket->number], SmsMessage::latest('id')->first()->params);
        $this->assertSame(1, SmsMessage::count());

        // A customer's followup → developers again.
        $service->addFollowup($ticket, $customer, '<p>More</p>', true);
        $this->assertSame(2, SmsMessage::where('purpose', 'ticket_to_developer')->count());

        // A developer reply that waits for the customer → the customer; one that does not wait → nothing.
        $service->addFollowup($ticket, $dev, '<p>Done?</p>', true);
        $this->assertDatabaseHas('sms_messages', ['mobile' => '+989120000002', 'template_id' => 24561, 'purpose' => 'reply_to_customer']);
        $this->assertSame([$ticket->number], SmsMessage::where('purpose', 'reply_to_customer')->first()->params);
        $service->addFollowup($ticket, $dev, '<p>FYI</p>', false);
        $this->assertSame(1, SmsMessage::where('purpose', 'reply_to_customer')->count());

        // A developer's own new ticket → no SMS.
        $service->create(['project_id' => $project->id, 'type' => 'task', 'priority' => 'medium', 'status' => 'backlog', 'title' => 'Internal'], $dev);
        $this->assertSame(3, SmsMessage::count());
    }
}
