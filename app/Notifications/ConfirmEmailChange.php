<?php

namespace App\Notifications;

use App\Models\EmailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the NEW (pending) email address only — never to the old one — per
 * "Canonical Email Change Lifecycle" step 7.
 */
class ConfirmEmailChange extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EmailChangeRequest $emailChangeRequest,
        private readonly string $plainToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('email-change.verify', [
            'emailChangeRequest' => $this->emailChangeRequest->id,
            'token' => $this->plainToken,
        ]);

        return (new MailMessage)
            ->subject('Confirm your new email address')
            ->line('Please confirm this email address to complete your account email change.')
            ->action('Confirm Email Address', $url)
            ->line('If you did not request this change, no action is required.');
    }
}
