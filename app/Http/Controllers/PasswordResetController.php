<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetLink;
use App\Models\PasswordOtp;
use App\Models\User;
use App\Sms\Sms;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Forgot password. One box takes an email or a mobile number.
 * - Email: a reset link (valid 60 minutes) is mailed by the queue worker.
 * - Mobile: a 6-digit code by SMS. The code is valid 2 minutes after each send. After the
 *   1:50 countdown the user can ask again: the 2nd request sends the same code by SMS, the 3rd
 *   one by a voice call. A correct code opens the "new password" step for 10 minutes.
 * Rate limits: 5 requests per email / mobile in 12 hours, 10 per IP in 24 hours (each channel).
 */
class PasswordResetController extends Controller
{
    public const OTP_TTL = 120;

    public const RESEND_AFTER = 110;

    public const MAX_SENDS = 3;

    public const MAX_ATTEMPTS = 5;

    public const VERIFIED_TTL = 600;

    public const PER_ACCOUNT = [5, 12 * 3600];

    public const PER_IP = [10, 24 * 3600];

    private const SESSION_KEY = 'password_otp_id';

    public function showRequest()
    {
        return view('auth.forgot');
    }

    public function sendRequest(Request $request)
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:255']], [], ['login' => __('auth.forgot_login_short')]);
        $login = Dates::latinDigits(trim($data['login']));

        return str_contains($login, '@')
            ? $this->sendEmail($request, Str::lower($login))
            : $this->sendCode($request, $login);
    }

    // ---- Email ------------------------------------------------------------------

    private function sendEmail(Request $request, string $email)
    {
        // Every try counts for the IP (also unknown addresses), so nobody can test many emails.
        $this->limit('pwd-mail-ip:'.$request->ip(), self::PER_IP);
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages(['login' => __('auth.forgot_email_not_found')]);
        }
        $this->checkActive($user);
        $this->limit('pwd-mail:'.$email, self::PER_ACCOUNT);

        $token = Password::broker()->createToken($user);
        Mail::to($user)->locale($user->locale ?: config('app.locale'))->send(new PasswordResetLink($user, $token));

        return back()->with('success', __('auth.reset_link_sent', ['email' => $user->email]));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.new-password', [
            'action' => route('password.update'),
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], ['password' => __('auth.new_password')]);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['password' => __('auth.reset_invalid')]);
        }

        return redirect()->route('login')->with('success', __('auth.password_reset_done'));
    }

    // ---- Mobile (OTP) -------------------------------------------------------------

    private function sendCode(Request $request, string $login)
    {
        $this->limit('pwd-sms-ip:'.$request->ip(), self::PER_IP);
        $mobile = Sms::normalize($login);
        $user = $mobile ? User::whereIn('mobile', self::mobileVariants($mobile))->first() : null;
        if (! $user) {
            throw ValidationException::withMessages(['login' => __('auth.forgot_mobile_not_found')]);
        }
        $this->checkActive($user);
        $this->limit('pwd-sms:'.$mobile, self::PER_ACCOUNT);

        // A new request starts again with a new code.
        PasswordOtp::where('user_id', $user->id)->whereNull('used_at')->delete();
        $otp = PasswordOtp::create([
            'user_id' => $user->id,
            'mobile' => $mobile,
            'code' => (string) random_int(100000, 999999),
            'sends' => 1,
            'last_sent_at' => now(),
            'expires_at' => now()->addSeconds(self::OTP_TTL),
        ]);
        Sms::send($mobile, 'otp', [], $otp->code);

        $request->session()->put(self::SESSION_KEY, $otp->id);

        return redirect()->route('password.otp')->with('success', __('auth.otp_sent', ['mobile' => self::mask($mobile)]));
    }

    public function showCode(Request $request)
    {
        $otp = $this->currentOtp($request);
        if (! $otp) {
            return redirect()->route('password.request')->withErrors(['login' => __('auth.session_expired')]);
        }

        return view('auth.otp', [
            'otp' => $otp,
            'mobile' => self::mask($otp->mobile),
            'wait' => max(0, self::RESEND_AFTER - (int) $otp->last_sent_at->diffInSeconds(now())),
            'canResend' => $otp->sends < self::MAX_SENDS,
            'nextByCall' => $otp->sends + 1 >= self::MAX_SENDS,
        ]);
    }

    /** Send the same code again: by SMS, and the last time by a voice call. */
    public function resend(Request $request)
    {
        $otp = $this->currentOtp($request);
        if (! $otp || $otp->verified_at) {
            return redirect()->route('password.request')->withErrors(['login' => __('auth.session_expired')]);
        }
        if ($otp->sends >= self::MAX_SENDS || $otp->attempts >= self::MAX_ATTEMPTS) {
            return back()->withErrors(['code' => __('auth.otp_no_more')]);
        }
        if ($otp->last_sent_at->diffInSeconds(now()) < self::RESEND_AFTER - 2) {
            return back()->withErrors(['code' => __('auth.otp_wait')]);
        }
        $this->limit('pwd-sms-ip:'.$request->ip(), self::PER_IP, 'code');
        $this->limit('pwd-sms:'.$otp->mobile, self::PER_ACCOUNT, 'code');

        $otp->update([
            'sends' => $otp->sends + 1,
            'last_sent_at' => now(),
            'expires_at' => now()->addSeconds(self::OTP_TTL),
        ]);
        $byCall = $otp->sends >= self::MAX_SENDS;
        Sms::send($otp->mobile, 'otp', [], $otp->code, $byCall ? 'ivr' : 'sms');

        return back()->with('success', __($byCall ? 'auth.otp_called' : 'auth.otp_sent', ['mobile' => self::mask($otp->mobile)]));
    }

    public function verify(Request $request)
    {
        $otp = $this->currentOtp($request);
        if (! $otp) {
            return redirect()->route('password.request')->withErrors(['login' => __('auth.session_expired')]);
        }
        $request->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => __('auth.otp_label')]);
        $code = preg_replace('/\D/', '', Dates::latinDigits($request->input('code')));

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => __('auth.otp_too_many')]);
        }
        if ($otp->isExpired()) {
            throw ValidationException::withMessages(['code' => __('auth.otp_expired')]);
        }
        if (! hash_equals($otp->code, $code)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => __('auth.otp_wrong')]);
        }

        $otp->update(['verified_at' => now()]);

        return redirect()->route('password.otp.new');
    }

    public function showNewPassword(Request $request)
    {
        if (! $this->verifiedOtp($request)) {
            return redirect()->route('password.request')->withErrors(['login' => __('auth.session_expired')]);
        }

        return view('auth.new-password', ['action' => route('password.otp.save'), 'token' => null, 'email' => null]);
    }

    public function saveNewPassword(Request $request)
    {
        $otp = $this->verifiedOtp($request);
        if (! $otp) {
            return redirect()->route('password.request')->withErrors(['login' => __('auth.session_expired')]);
        }
        $data = $request->validate(['password' => ['required', 'confirmed', PasswordRule::min(8)]], [], ['password' => __('auth.new_password')]);

        $otp->user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        $otp->update(['used_at' => now()]);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->with('success', __('auth.password_reset_done'));
    }

    // ---- Helpers ------------------------------------------------------------------

    private function currentOtp(Request $request): ?PasswordOtp
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? PasswordOtp::whereKey($id)->whereNull('used_at')->first() : null;
    }

    private function verifiedOtp(Request $request): ?PasswordOtp
    {
        $otp = $this->currentOtp($request);

        return $otp?->verified_at && $otp->verified_at->diffInSeconds(now()) < self::VERIFIED_TTL ? $otp : null;
    }

    private function checkActive(User $user): void
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('auth.inactive')]);
        }
    }

    /** Count one request on $key; refuse when the limit is reached. */
    private function limit(string $key, array $rule, string $field = 'login'): void
    {
        [$max, $seconds] = $rule;
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages([$field => __('auth.forgot_throttle', ['minutes' => $minutes])]);
        }
        RateLimiter::hit($key, $seconds);
    }

    /** The ways a mobile number may be saved on a user: +989121234567, 09121234567, 9121234567, 989121234567. */
    public static function mobileVariants(string $e164): array
    {
        $digits = ltrim($e164, '+');
        $variants = [$e164, $digits];
        if (str_starts_with($e164, '+98')) {
            $local = substr($e164, 3);
            array_push($variants, '0'.$local, $local, '0098'.$local);
        }

        return $variants;
    }

    /** +989121234567 → 0912***4567 */
    public static function mask(string $mobile): string
    {
        $local = str_starts_with($mobile, '+98') ? '0'.substr($mobile, 3) : $mobile;

        return substr($local, 0, 4).str_repeat('*', max(0, strlen($local) - 8)).substr($local, -4);
    }
}
