<?php

namespace App\Enums;

enum TimelineEventType: string
{
    case Aanvraag = 'aanvraag';
    case Email = 'email';
    case Telefoon = 'telefoon';
    case Notitie = 'notitie';
    case VoiceMemo = 'voice_memo';
    case Afspraak = 'afspraak';
    case Offerte = 'offerte';
    case Wijziging = 'wijziging';
    case Document = 'document';
    case Betaling = 'betaling';
    case Projectupdate = 'projectupdate';
    case Whatsapp = 'whatsapp';
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::Aanvraag => 'Aanvraag',
            self::Email => 'E-mail',
            self::Telefoon => 'Telefoongesprek',
            self::Notitie => 'Notitie',
            self::VoiceMemo => 'Voice memo',
            self::Afspraak => 'Afspraak',
            self::Offerte => 'Offerte',
            self::Wijziging => 'Wijziging',
            self::Document => 'Document',
            self::Betaling => 'Betaling',
            self::Projectupdate => 'Projectupdate',
            self::Whatsapp => 'WhatsApp',
            self::Intern => 'Interne actie',
        };
    }

    /**
     * Icoonnaam voor de x-icon Blade-component.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Aanvraag => 'arrow-down-tray',
            self::Email => 'envelope',
            self::Telefoon => 'phone',
            self::Notitie => 'pencil-square',
            self::VoiceMemo => 'microphone',
            self::Afspraak => 'calendar',
            self::Offerte => 'document-text',
            self::Wijziging => 'pencil',
            self::Document => 'paper-clip',
            self::Betaling => 'currency-euro',
            self::Projectupdate => 'wrench-screwdriver',
            self::Whatsapp => 'chat-bubble',
            self::Intern => 'cog',
        };
    }
}
