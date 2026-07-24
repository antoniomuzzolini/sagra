<?php

namespace App\Notifications;

use App\Models\Area;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * "È uscito il tabellone": new shifts opened in an area. Deliberately
 * area-level (not per shift) — the sender throttles per person/area, so a
 * batch of created shifts reads as one nudge.
 */
class NewShiftsAvailable extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Area $area) {}

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
            ->title("Nuovi turni in {$this->area->name}!")
            ->body('Dai un\'occhiata e prenotati.')
            ->data(['url' => route('volunteer.home')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nuovi turni in {$this->area->name}")
            ->greeting("Ciao {$notifiable->name}!")
            ->line("Sono usciti nuovi turni per {$this->area->name} ({$this->area->event->name}).")
            ->action('Prenotati', route('volunteer.home'))
            ->salutation("A presto,\n{$this->area->event->name}");
    }
}
