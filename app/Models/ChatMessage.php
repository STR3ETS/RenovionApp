<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_channel_id', 'user_id', 'body', 'attachment_path', 'attachment_name', 'nova'])]
class ChatMessage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nova' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChatChannel::class, 'chat_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFromNova(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Payload voor de Alpine-chatcomponent.
     *
     * @return array<string, mixed>
     */
    /** Elke afzender een eigen naamkleur in het gesprek, zoals in het prototype. */
    public const SENDER_COLORS = ['#C2500C', '#3863A0', '#1A7D51', '#7C5CD6', '#C2506D', '#0E8FC2', '#A86A06', '#2B8C8C', '#8B5E3C', '#D23A3F'];

    public function toChatArray(?User $viewer = null): array
    {
        return [
            'id' => $this->id,
            'nova' => $this->isFromNova() ? [
                'preview' => $this->nova['preview'] ?? null,
                'action' => $this->nova['action'] ?? null,
                'executed' => (bool) ($this->nova['executed'] ?? false),
                'url' => $this->nova['url'] ?? null,
            ] : null,
            'user' => $this->isFromNova() ? null : [
                'name' => $this->user?->name ?? 'Verwijderde gebruiker',
                'initial' => str($this->user?->name ?? '?')->substr(0, 1)->upper()->toString(),
            ],
            'mine' => $viewer !== null && $this->user_id === $viewer->id,
            'body' => $this->body,
            'attachment_url' => $this->attachment_path ? route('chat.attachment', $this) : null,
            'attachment_name' => $this->attachment_name,
            'time' => $this->created_at->translatedFormat('j M H:i'),
            // Voor de dagscheiding en de kleine tijd in de bubbel.
            'date' => $this->created_at->toDateString(),
            'day' => $this->created_at->isToday()
                ? 'Vandaag'
                : str($this->created_at->translatedFormat('D j M'))->ucfirst()->replace('.', '')->toString(),
            'clock' => $this->created_at->format('H:i'),
            'color' => self::SENDER_COLORS[($this->user_id ?? 0) % count(self::SENDER_COLORS)],
        ];
    }
}
