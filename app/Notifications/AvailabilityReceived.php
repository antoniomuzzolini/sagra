<?php

namespace App\Notifications;

use App\Models\Person;
use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells the area managers someone volunteered for one of their
 * shifts, so they can confirm it while the intention is fresh.
 */
class AvailabilityReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Person $volunteer, public Shift $shift) {}

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
            ->title("{$this->volunteer->name} si è offerto")
            ->body("{$this->shift->area->name}, {$this->when()} — tocca per confermare")
            ->data(['url' => route('volunteer.home')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nuova disponibilità per {$this->shift->area->name}")
            ->greeting("Ciao {$notifiable->name}!")
            ->line("{$this->volunteer->name} si è offerto per il turno di {$this->shift->area->name} di {$this->when()}.")
            ->action('Vai a confermare', route('volunteer.home'))
            ->salutation("A presto,\n{$this->shift->area->event->name}");
    }

    private function when(): string
    {
        return $this->shift->starts_at->locale('it')->isoFormat('dddd D MMMM')
            .', ore '.$this->shift->starts_at->format('H:i').'–'.$this->shift->ends_at->format('H:i');
    }
}
