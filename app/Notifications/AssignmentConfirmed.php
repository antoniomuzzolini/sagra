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
 * Tells a person they've been confirmed on a shift — the answer to
 * their "ci sono" (or the promotion from the waiting list of a full
 * shift, e.g. when covering a substitution).
 */
class AssignmentConfirmed extends Notification implements ShouldQueue
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
            ->title('Sei confermato! ✅')
            ->body("{$this->shift->area->name}, {$this->when()}")
            ->data(['url' => route('volunteer.home')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Confermato: {$this->shift->area->name}, {$this->when()}")
            ->greeting("Ciao {$notifiable->name}!")
            ->line("Sei confermato per il turno di {$this->shift->area->name} di {$this->when()}.")
            ->action('I tuoi turni', route('volunteer.home'))
            ->salutation("A presto,\n{$this->shift->area->event->name}");
    }

    private function when(): string
    {
        return $this->shift->starts_at->locale('it')->isoFormat('dddd D MMMM')
            .', ore '.$this->shift->starts_at->format('H:i').'–'.$this->shift->ends_at->format('H:i');
    }
}
