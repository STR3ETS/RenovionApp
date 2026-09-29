<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['type', 'name', 'project_id', 'created_by'])]
class ChatChannel extends Model
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at')->withTimestamps();
    }

    public function isProjectChannel(): bool
    {
        return $this->type === 'project';
    }

    public function displayName(): string
    {
        return $this->isProjectChannel() ? ($this->project?->name ?? $this->name) : $this->name;
    }

    /**
     * Teamkanalen zijn voor het hele team; projectkanalen volgen de
     * projecttoegang (uitvoerders alleen eigen projecten, klant nooit).
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->cannot('internal')) {
            return false;
        }

        return $this->isProjectChannel()
            ? ($this->project?->isAccessibleBy($user) ?? false)
            : true;
    }

    /**
     * Alle kanalen die deze gebruiker mag zien, met unread counters.
     *
     * @return Collection<int, self>
     */
    public static function forUser(User $user)
    {
        return self::query()
            ->when($user->cannot('manage-crm'), fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('type', 'team')
                    ->orWhereHas('project.craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $user->id))))
            ->with(['project', 'readers' => fn ($query) => $query->where('users.id', $user->id)])
            ->withMax('messages as last_message_at', 'created_at')
            ->orderBy('type')
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function unreadCountFor(User $user): int
    {
        $lastRead = $this->readers->firstWhere('id', $user->id)?->pivot?->last_read_at;

        return $this->messages()
            ->where(fn ($query) => $query->where('user_id', '!=', $user->id)->orWhereNull('user_id'))
            ->when($lastRead !== null, fn ($query) => $query->where('created_at', '>', $lastRead))
            ->count();
    }

    public function markReadFor(User $user): void
    {
        $this->readers()->syncWithoutDetaching([$user->id => ['last_read_at' => now()]]);
        Cache::forget('chat-unread.'.$user->id);
    }

    /**
     * Totaal ongelezen voor de navigatiebadge (kort gecachet).
     */
    public static function unreadTotalFor(User $user): int
    {
        return (int) Cache::remember('chat-unread.'.$user->id, 60, fn () => self::forUser($user)
            ->sum(fn (self $channel) => $channel->unreadCountFor($user)));
    }
}
