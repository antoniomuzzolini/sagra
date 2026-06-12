<?php

namespace App\Notifications;

use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ShiftReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Shift $shift) {}

    public function via(object $notifiable): array
    {
        // Web push is the primary channel (D10: volunteers live on
        // their phones); mail is the fallback for those who left one.
        return array_values(array_filter([
            $notifiable->pushSubscriptions()->exists() ? WebPushChannel::class : null,
            $notifiable->email ? 'mail' : null,
        ]));
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $shift = $this->shift;
        $day = $shift->starts_at->locale('it')->isoFormat('dddd D MMMM');
        $hours = $shift->starts_at->format('H:i').'–'.$shift->ends_at->format('H:i');

        return (new WebPushMessage)
            ->title("Domani tocca a te: {$shift->area->name}")
            ->body("{$day}, ore {$hours}".($shift->notes ? " — {$shift->notes}" : ''))
            ->data(['url' => route('volunteer.home')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shift = $this->shift;
        $day = $shift->starts_at->locale('it')->isoFormat('dddd D MMMM');
        $hours = $shift->starts_at->format('H:i').'–'.$shift->ends_at->format('H:i');

        return (new MailMessage)
            ->subject("Promemoria turno: {$shift->area->name}, {$day}")
            ->greeting("Ciao {$notifiable->name}!")
            ->line("Ti ricordiamo il tuo turno di domani in {$shift->area->name}: {$day}, ore {$hours}.")
            ->lineIf((bool) $shift->notes, "Note: {$shift->notes}")
            ->salutation("A presto,\n{$shift->area->event->name}");
    }
}
