<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A self-test from the Impostazioni page: proves push (and email, when
 * configured) actually reach the organizer's device.
 */
class TestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->pushSubscriptions()->exists() ? WebPushChannel::class : null,
            $notifiable->email ? 'mail' : null,
        ]));
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Le notifiche funzionano! 🎉')
            ->body('Questa è una notifica di prova.')
            ->data(['url' => route('dashboard')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Notifica di prova')
            ->greeting("Ciao {$notifiable->name}!")
            ->line('Le notifiche email funzionano: questa è una prova.');
    }
}
