<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * F-001 A8: queued, in Bahasa; single-use link valid for 60 minutes. The
 * token travels as a query parameter so log redaction catches it (A13).
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.mail.reset_subject'))
            ->greeting(__('account.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('account.mail.reset_line'))
            ->action(__('account.mail.reset_action'), $this->resetUrl($notifiable))
            ->line(__('account.mail.reset_expire', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('account.mail.reset_ignore'))
            ->salutation(__('account.mail.salutation'));
    }

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }
}
