<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\ChatMessage;
use App\Notifications\Channels\PushChannel;
use Illuminate\Notifications\Notification;

/**
 * Iemand noemt je met @naam in de teamchat (briefing §11).
 */
class ChatMention extends Notification
{
    public function __construct(public ChatMessage $message) {}

    public function type(): NotificationType
    {
        return NotificationType::ChatMention;
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
            'title' => ($this->message->user?->name ?? 'Iemand').' noemde je in '.$this->message->channel->name,
            'body' => str($this->message->body ?? '')->squish()->limit(140)->toString(),
            'url' => route('chat.show', $this->message->channel, false),
            'icon' => 'chat-bubble',
        ];
    }
}
