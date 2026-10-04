<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Password recovery link. Sent by the queue worker (retries after 1, 5, 10, 30 minutes). */
class PasswordResetLink extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 600, 1800];

    public function __construct(public User $user, public string $token)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.reset.subject').' · '.(parse_url(config('app.url'), PHP_URL_HOST) ?: __('app.name')));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-reset', with: [
            'url' => route('password.reset', ['token' => $this->token, 'email' => $this->user->email]),
            'minutes' => config('auth.passwords.users.expire'),
        ]);
    }
}
