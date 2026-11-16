<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Generic in-platform notification stored in the database (shown in the bell menu and via the API). */
class PlatformNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $kind = 'info',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'kind' => $this->kind];
    }
}
