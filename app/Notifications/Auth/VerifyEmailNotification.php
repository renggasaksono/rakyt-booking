<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * F-001 A3, A10: queued, in Bahasa; the signed link expires after
 * config('auth.verification.expire') minutes (60).
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.mail.verify_subject'))
            ->greeting(__('account.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('account.mail.verify_line'))
            ->action(__('account.mail.verify_action'), $this->verificationUrl($notifiable))
            ->line(__('account.mail.verify_expire', ['minutes' => config('auth.verification.expire', 60)]))
            ->line(__('account.mail.verify_ignore'))
            ->salutation(__('account.mail.salutation'));
    }
}
