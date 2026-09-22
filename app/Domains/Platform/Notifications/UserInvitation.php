<?php

declare(strict_types=1);

namespace App\Domains\Platform\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a new user to set their password and sign in.
 * The link uses the password-reset token, so it expires with the broker's expiry (60 minutes by default).
 */
final class UserInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $companyName,
        public readonly ?string $invitedBy = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->email], false));

        return (new MailMessage)
            ->subject("You've been invited to {$this->companyName} on Thabekhulu")
            ->greeting("Hello {$notifiable->name},")
            ->line(($this->invitedBy ? "{$this->invitedBy} has" : 'You have been').
                " given you access to {$this->companyName} on Thabekhulu Development Software.")
            ->action('Set your password', $url)
            ->line('For your security this link expires in 60 minutes. If it has expired, use "Forgot password" on the sign-in page.');
    }
}
