<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use Illuminate\Notifications\Notification;

/**
 * "Testmelding sturen" op de instellingenpagina: alleen push, geen vastlegging, geen voorkeur.
 */
class TestPush extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [PushChannel::class];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Testmelding van Renovion',
            'body' => 'Pushmeldingen werken op dit toestel.',
            'url' => route('notification-settings.edit', absolute: false),
            'icon' => 'bell',
            // Gewone meldingen verschijnen niet als de app open staat; deze wel (native.js).
            'push_data' => ['test' => '1'],
        ];
    }
}
