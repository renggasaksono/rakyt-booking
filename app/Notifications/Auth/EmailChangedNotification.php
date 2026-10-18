<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * F-001 A11: sent to the old address after the sign-in email changed.
 */
class EmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $maskedNewEmail) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.mail.email_changed_subject'))
            ->line(__('account.mail.email_changed_line', ['email' => $this->maskedNewEmail]))
            ->line(__('account.mail.email_changed_warning'))
            ->salutation(__('account.mail.salutation'));
    }

    /**
     * "agus.santoso@gmail.com" → "ag***@gmail.com"
     */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1))).'***@'.$domain;
    }
}
