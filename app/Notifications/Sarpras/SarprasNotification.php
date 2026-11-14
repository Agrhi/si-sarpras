<?php

namespace App\Notifications\Sarpras;

use Illuminate\Notifications\Notification;

abstract class SarprasNotification extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $message,
        public string $url,
        public string $type,
        public array $context = [],
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'url' => $this->url,
            'type' => $this->type,
            ...$this->context,
        ];
    }
}
