<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Quote;
use App\Notifications\Channels\PushChannel;
use Illuminate\Notifications\Notification;

/**
 * Klant ondertekent de offerte via de klantlink (briefing §6); er is meteen een project.
 */
class QuoteSigned extends Notification
{
    public function __construct(public Quote $quote) {}

    public function type(): NotificationType
    {
        return NotificationType::QuoteSigned;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', PushChannel::class];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Offerte '.$this->quote->number.' ondertekend',
            'body' => $this->quote->customer->name.' tekende als '.$this->quote->signed_name
                .', € '.number_format((float) $this->quote->total, 0, ',', '.').'. Het project staat klaar.',
            'url' => $this->quote->project ? route('projects.show', $this->quote->project, false) : route('quotes.show', $this->quote, false),
            'icon' => 'document-text',
        ];
    }
}
