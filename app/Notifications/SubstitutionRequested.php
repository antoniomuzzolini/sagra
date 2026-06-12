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
 * Tells the area managers an assigned volunteer can no longer make
 * it: a hole is about to open, better to know right away.
 */
class SubstitutionRequested extends Notification implements ShouldQueue
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
            ->title("{$this->volunteer->name} cerca un sostituto")
            ->body("{$this->shift->area->name}, {$this->when()}")
            ->data(['url' => route('volunteer.home')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Richiesta di sostituzione in {$this->shift->area->name}")
            ->greeting("Ciao {$notifiable->name}!")
            ->line("{$this->volunteer->name} non può più coprire il turno di {$this->shift->area->name} di {$this->when()} e cerca un sostituto.")
            ->action('Vai al turno', route('volunteer.home'))
            ->salutation("A presto,\n{$this->shift->area->event->name}");
    }

    private function when(): string
    {
        return $this->shift->starts_at->locale('it')->isoFormat('dddd D MMMM')
            .', ore '.$this->shift->starts_at->format('H:i').'–'.$this->shift->ends_at->format('H:i');
    }
}
