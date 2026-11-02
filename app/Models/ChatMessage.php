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
            'author' => $this->user_id ?? 'nova',
            'mine' => $viewer !== null && $this->user_id === $viewer->id,
            'body' => $this->body,
            'attachment_url' => $this->attachment_path ? route('chat.attachment', $this) : null,
            'attachment_name' => $this->attachment_name,
            'at' => $this->created_at->timestamp,
            'day' => $this->dayLabel(),
            'time' => $this->created_at->format('H:i'),
        ];
    }

    /**
     * Dagscheiding in het gesprek.
     */
    public function dayLabel(): string
    {
        return match (true) {
            $this->created_at->isToday() => 'Vandaag',
            $this->created_at->isYesterday() => 'Gisteren',
            $this->created_at->isCurrentYear() => $this->created_at->translatedFormat('j F'),
            default => $this->created_at->translatedFormat('j F Y'),
        };
    }

    /**
     * Tijd in de kanalenlijst: vandaag de klok, daarna de dag.
     */
    public function listTime(): string
    {
        return match (true) {
            $this->created_at->isToday() => $this->created_at->format('H:i'),
            $this->created_at->isYesterday() => 'Gisteren',
            default => $this->created_at->translatedFormat('j M'),
        };
    }

    /**
     * Regel in de kanalenlijst: wie wat zei.
     */
    public function preview(User $viewer): string
    {
        $sender = match (true) {
            $this->isFromNova() => 'Nova',
            $this->user_id === $viewer->id => 'Jij',
            default => str($this->user?->name ?? 'Onbekend')->before(' ')->toString(),
        };

        return $sender.': '.($this->body ?: 'Foto');
    }
}
