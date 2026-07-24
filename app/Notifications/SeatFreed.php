<?php

namespace App\Notifications;

use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells the area's volunteers a seat just freed up on a shift that was
 * covered — first come, first serve ("turno scoperto", MVP notification).
 */
class SeatFreed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Shift $shift) {}

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
            ->title('Si è liberato un posto!')
            ->body("{$this->shift->area->name}, {$this->when()} — Ci sei?")
            ->data(['url' => route('volunteer.home')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Si è liberato un posto: {$this->shift->area->name}, {$this->when()}")
            ->greeting("Ciao {$notifiable->name}!")
            ->line("Si è liberato un posto nel turno di {$this->shift->area->name} di {$this->when()}.")
            ->action('Ci sono!', route('volunteer.home'))
            ->salutation("A presto,\n{$this->shift->area->event->name}");
    }

    private function when(): string
    {
        return $this->shift->starts_at->locale('it')->isoFormat('dddd D MMMM')
            .', ore '.$this->shift->starts_at->format('H:i').'–'.$this->shift->ends_at->format('H:i');
    }
}
