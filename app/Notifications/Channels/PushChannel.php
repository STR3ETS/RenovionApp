<?php

namespace App\Notifications\Channels;

use App\Services\FirebaseMessaging;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Stuurt een melding als push naar alle telefoons van de ontvanger. Gebruikt dezelfde
 * toArray() als de database (title/body/url); een mislukte push breekt nooit de actie zelf.
 */
class PushChannel
{
    public function __construct(private FirebaseMessaging $firebase) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $this->firebase->isConfigured() || ! method_exists($notifiable, 'deviceTokens')) {
            return;
        }

        // Meldingsvoorkeuren (instellingen > Meldingen): uitgezette soorten niet pushen.
        if (method_exists($notification, 'type') && method_exists($notifiable, 'wantsPush') && ! $notifiable->wantsPush($notification->type())) {
            return;
        }

        $data = $notification->toArray($notifiable);

        foreach ($notifiable->deviceTokens as $device) {
            try {
                $geldig = $this->firebase->send($device->token, $data['title'], $data['body'] ?? '', [
                    'url' => $data['url'] ?? '/',
                    ...($data['push_data'] ?? []),
                ]);

                if (! $geldig) {
                    $device->delete();
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
