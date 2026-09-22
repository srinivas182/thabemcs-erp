<?php

declare(strict_types=1);

namespace App\Domains\Platform\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
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
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, url: string|null, level: string}
     */
    public function toArray(User $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'level' => $this->level];
    }
}
