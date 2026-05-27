<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLoginNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $ipAddress,
        public string $userAgent,
        public \DateTimeInterface $occurredAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New sign-in to your account')
            ->line('We noticed a sign-in from a device we have not seen before.')
            ->line('IP address: '.$this->ipAddress)
            ->line('Device: '.$this->userAgent)
            ->line('Time: '.$this->occurredAt->format('Y-m-d H:i:s T'))
            ->line('If this was you, no action is needed. Otherwise, change your password immediately.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
