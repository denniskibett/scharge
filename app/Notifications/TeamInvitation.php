<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "You've been added to <company> on scharge": link to set their own password (pattern from Brevemaster). */
class TeamInvitation extends Notification
{
    use Queueable;

    public function __construct(public string $company, public string $invitedBy, public string $roleLabel, public string $url)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been added to {$this->company} on scharge")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->invitedBy} added you to {$this->company} on scharge as {$this->roleLabel}.")
            ->action('Set your password', $this->url)
            ->line('The link works for 60 minutes. If it has expired, use "Forgot password" on the login page with this email address.');
    }
}
