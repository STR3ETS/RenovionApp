<?php

namespace App\Enums;

use App\Models\User;

/**
 * Soorten pushmeldingen die iemand per stuk aan of uit kan zetten (briefing §19).
 */
enum NotificationType: string
{
    case ChatMention = 'chat_mention';
    case TaskAssigned = 'task_assigned';
    case QuoteSigned = 'quote_signed';

    public function label(): string
    {
        return match ($this) {
            self::ChatMention => 'Iemand noemt je in de chat',
            self::TaskAssigned => 'Iemand geeft je een taak',
            self::QuoteSigned => 'Een klant tekent een offerte',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ChatMention => 'Als een collega je met @voornaam noemt in de teamchat of een projectchat.',
            self::TaskAssigned => 'Als een collega of Nova een taak op jouw naam zet. Taken die je jezelf geeft, melden niet.',
            self::QuoteSigned => 'Als een klant via de klantlink ondertekent. Gaat naar de verkoper van de aanvraag, anders naar de admins.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ChatMention => 'chat-bubble',
            self::TaskAssigned => 'clipboard-check',
            self::QuoteSigned => 'document-text',
        };
    }

    /**
     * Alleen de soorten die deze gebruiker ooit kan krijgen (offertes gaan niet naar uitvoerders).
     *
     * @return array<int, self>
     */
    public static function forUser(User $user): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type) => $type !== self::QuoteSigned || $user->can('manage-crm'),
        ));
    }
}
