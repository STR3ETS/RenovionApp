<?php

namespace App\Enums;

enum ActionSource: string
{
    case Handmatig = 'handmatig';
    case Voice = 'voice';
    case Nova = 'nova';
    case Automation = 'automation';
    case Website = 'website';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Handmatig => 'Handmatig',
            self::Voice => 'Voice command',
            self::Nova => 'Nova voorstel',
            self::Automation => 'Automation',
            self::Website => 'Website',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
