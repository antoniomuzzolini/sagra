<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkRecovery extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url, public string $tenantName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Il tuo nuovo link d'accesso ai turni")
            ->greeting("Ciao {$notifiable->name}!")
            ->line('Ecco il tuo nuovo link personale: toccalo per tornare ai tuoi turni.')
            ->action('Entra nei miei turni', $this->url)
            ->line('Se non l\'hai chiesto tu, puoi ignorare questa email.')
            ->salutation("A presto,\n{$this->tenantName}");
    }
}
