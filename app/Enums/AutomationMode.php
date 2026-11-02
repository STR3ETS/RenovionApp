<?php

namespace App\Enums;

/**
 * Briefing §12: per automation instelbaar - alleen signaleren / eerst
 * bevestigen / automatisch uitvoeren (of helemaal uit).
 */
enum AutomationMode: string
{
    case Uit = 'uit';
    case Signaleren = 'signaleren';
    case Bevestigen = 'bevestigen';
    case Automatisch = 'automatisch';

    public function label(): string
    {
        return match ($this) {
            self::Uit => 'Uit',
            self::Signaleren => 'Alleen signaleren',
            self::Bevestigen => 'Eerst bevestigen',
            self::Automatisch => 'Automatisch uitvoeren',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Uit => 'Deze regel doet niets.',
            self::Signaleren => 'Nova meldt het signaal in de teamchat, maar onderneemt geen actie.',
            self::Bevestigen => 'Nova zet een voorstel klaar in de teamchat; iemand bevestigt met "Ja, doe maar".',
            self::Automatisch => 'De actie wordt direct uitgevoerd en vastgelegd in de audit trail.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Uit => 'bg-white/10 text-slate-400',
            self::Signaleren => 'bg-amber-500/15 text-amber-300',
            self::Bevestigen => 'bg-brand-500/15 text-brand-300',
            self::Automatisch => 'bg-green-500/15 text-green-300',
        };
    }
}
