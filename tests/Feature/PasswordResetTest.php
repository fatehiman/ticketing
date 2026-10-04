<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLink;
use App\Models\PasswordOtp;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        $this->user = User::factory()->customer()->create(['email' => 'ali@example.com', 'mobile' => '09121234567', 'locale' => 'en']);
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee(__('auth.forgot_login'));
    }

    public function test_email_link_flow(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['login' => 'nobody@example.com'])->assertSessionHasErrors('login');
        Mail::assertNothingQueued();

        $this->post('/forgot-password', ['login' => 'Ali@Example.com'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $token = null;
        Mail::assertQueued(PasswordResetLink::class, function (PasswordResetLink $mail) use (&$token) {
            $token = $mail->token;

            return $mail->hasTo('ali@example.com') && $mail->locale === 'en';
        });

        // The mail names the site and the link opens the reset page.
        $html = (new PasswordResetLink($this->user, $token))->render();
        $this->assertStringContainsString(parse_url(config('app.url'), PHP_URL_HOST), $html);
        $this->assertStringContainsString('/reset-password/'.$token, $html);

        $this->get('/reset-password/'.$token.'?email=ali@example.com')->assertOk();
        $this->post('/reset-password', ['token' => 'wrong', 'email' => 'ali@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('password');
        $this->post('/reset-password', ['token' => $token, 'email' => 'ali@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('newpass123', $this->user->fresh()->password));
    }

    public function test_email_rate_limit_per_address(): void
    {
        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['login' => 'ali@example.com'])->assertSessionHasNoErrors();
        }
        $this->post('/forgot-password', ['login' => 'ali@example.com'])->assertSessionHasErrors('login');
        Mail::assertQueuedCount(5);
    }

    public function test_ip_rate_limit_counts_unknown_addresses_too(): void
    {
        Mail::fake();
        for ($i = 0; $i < 10; $i++) {
            $this->post('/forgot-password', ['login' => "x$i@example.com"]);
        }
        $this->post('/forgot-password', ['login' => 'ali@example.com'])->assertSessionHasErrors('login');
        Mail::assertNothingQueued();
    }

    public function test_mobile_code_flow(): void
    {
        $this->post('/forgot-password', ['login' => '09129999999'])->assertSessionHasErrors('login');

        $this->post('/forgot-password', ['login' => '۰۹۱۲۱۲۳۴۵۶۷'])->assertRedirect(route('password.otp'));
        $otp = PasswordOtp::firstOrFail();
        $sms = SmsMessage::firstOrFail();
        $this->assertSame('+989121234567', $sms->mobile);
        $this->assertSame(3, $sms->template_id);
        $this->assertSame($otp->code, $sms->code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp->code);

        $this->get('/forgot-password/code')->assertOk()->assertSee('0912***4567')->assertSee('data-countdown="110"', false);
        $this->get('/forgot-password/new')->assertRedirect(route('password.request')); // not verified yet

        $wrong = $otp->code === '000000' ? '111111' : '000000';
        $this->post('/forgot-password/code', ['code' => $wrong])->assertSessionHasErrors('code');
        $this->post('/forgot-password/code', ['code' => $otp->code])->assertRedirect(route('password.otp.new'));

        $this->get('/forgot-password/new')->assertOk();
        $this->post('/forgot-password/new', ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/forgot-password/new', ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('newpass123', $this->user->fresh()->password));
        $this->assertNotNull($otp->fresh()->used_at);

        $this->post('/login', ['login' => '09121234567', 'password' => 'newpass123'])->assertRedirect('/');
    }

    public function test_resend_sends_the_same_code_then_calls(): void
    {
        $this->post('/forgot-password', ['login' => '09121234567']);
        $otp = PasswordOtp::firstOrFail();

        // Too early: the countdown is 1:50.
        $this->post('/forgot-password/resend')->assertSessionHasErrors('code');
        $this->assertSame(1, SmsMessage::count());

        $this->travel(111)->seconds();
        $this->post('/forgot-password/resend')->assertSessionHasNoErrors();
        $second = SmsMessage::latest('id')->first();
        $this->assertSame(['sms', $otp->code], [$second->method, $second->code]);
        $this->get('/forgot-password/code')->assertSee(__('auth.otp_call'));

        // The first code still works after 2:30 because each send gives 2 more minutes.
        $this->travel(111)->seconds();
        $this->post('/forgot-password/resend')->assertSessionHasNoErrors();
        $third = SmsMessage::latest('id')->first();
        $this->assertSame(['ivr', $otp->code, 2], [$third->method, $third->code, $third->template_id]);

        $this->travel(111)->seconds();
        $this->post('/forgot-password/resend')->assertSessionHasErrors('code'); // no 4th send
        $this->assertSame(3, SmsMessage::count());

        $this->post('/forgot-password/code', ['code' => $otp->code])->assertRedirect(route('password.otp.new'));
    }

    public function test_code_expires_after_two_minutes_and_wrong_tries_are_limited(): void
    {
        $this->post('/forgot-password', ['login' => '09121234567']);
        $otp = PasswordOtp::firstOrFail();

        $this->travel(121)->seconds();
        $this->post('/forgot-password/code', ['code' => $otp->code])->assertSessionHasErrors(['code' => __('auth.otp_expired')]);

        $this->travelBack();
        $this->post('/forgot-password', ['login' => '09121234567']);
        $otp = PasswordOtp::firstOrFail();
        $wrong = $otp->code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password/code', ['code' => $wrong]);
        }
        $this->post('/forgot-password/code', ['code' => $otp->code])->assertSessionHasErrors(['code' => __('auth.otp_too_many')]);
    }

    public function test_mobile_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['login' => '09121234567'])->assertSessionHasNoErrors();
        }
        $this->post('/forgot-password', ['login' => '09121234567'])->assertSessionHasErrors('login');
        $this->assertSame(5, SmsMessage::count());
    }
}
