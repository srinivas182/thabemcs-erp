<?php

declare(strict_types=1);

namespace App\Domains\Platform\Notifications;

use App\Domains\Platform\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A general in-app notification shown in the notification centre.
 * Modules send their own typed notifications using the same data shape.
 */
final class SystemMessage extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $level = 'info',
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        // Always in the inbox; by email too unless the person asked for a daily summary instead.
        $preference = NotificationPreference::query()->where('user_id', $notifiable->id)->first();
        $byEmail = $preference === null ? false : ($preference->email_immediately && ! $preference->daily_digest);

        return $byEmail && $notifiable->email !== null ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->title)->greeting('Hello '.$notifiable->name)->line($this->body);

        return $this->url === null ? $message : $message->action('Open it', url($this->url));
    }

    /**
     * @return array{title: string, body: string, url: string|null, level: string}
     */
    public function toArray(User $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'level' => $this->level];
    }
}
