<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
     * Collega's die met @voornaam genoemd worden (zelfde patroon als de highlight in app.js),
     * zonder de afzender en alleen wie het kanaal mag zien.
     *
     * @return Collection<int, User>
     */
    public function mentionedUsers(): Collection
    {
        preg_match_all('/@([\wÀ-ÿ-]+)/u', (string) $this->body, $matches);
        $names = collect($matches[1])->map(fn (string $name) => mb_strtolower($name))->unique();

        if ($names->isEmpty()) {
            return new Collection;
        }

        return User::query()
            ->where('role', '!=', UserRole::Klant)
            ->whereKeyNot($this->user_id)
            ->get()
            ->filter(fn (User $user) => $names->contains(mb_strtolower(str($user->name)->before(' ')->toString()))
                && $this->channel->isAccessibleBy($user))
            ->values();
    }

    /**
     * Payload voor de Alpine-chatcomponent.
     *
     * @return array<string, mixed>
     */
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
        ];
    }
}
