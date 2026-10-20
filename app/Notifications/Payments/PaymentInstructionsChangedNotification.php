<?php

namespace App\Notifications\Payments;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * F-006 AC-24 / ADR-0018: sent to the sign-in email whenever the organization's
 * payment instructions change, so a hijacked change is noticed.
 */
class PaymentInstructionsChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $organizationName) {}

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
            ->subject(__('payment.mail.changed_subject'))
            ->line(__('payment.mail.changed_line', ['name' => $this->organizationName]))
            ->line(__('payment.mail.changed_warning'))
            ->salutation(__('account.mail.salutation'));
    }
}
