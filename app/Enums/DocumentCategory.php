<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Tekening = 'tekening';
    case Foto = 'foto';
    case Offerte = 'offerte';
    case Calculatie = 'calculatie';
    case Factuur = 'factuur';
    case Contract = 'contract';
    case Technisch = 'technisch';
    case Oplevering = 'oplevering';
    case Veiligheid = 'veiligheid';
    case Garantie = 'garantie';
    case Vergunning = 'vergunning';
    case Voorwaarden = 'voorwaarden';
    case Keuring = 'keuring';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Tekening => 'Bouwtekening',
            self::Foto => 'Foto',
            self::Offerte => 'Offerte',
            self::Calculatie => 'Calculatie',
            self::Factuur => 'Factuur',
            self::Contract => 'Overeenkomst',
            self::Technisch => 'Technische documentatie',
            self::Oplevering => 'Opleverdocument',
            self::Veiligheid => 'Veiligheid en V en G',
            self::Garantie => 'Garantiedocument',
            self::Vergunning => 'Vergunning',
            self::Voorwaarden => 'Algemene voorwaarden',
            self::Keuring => 'Keuring en meetrapport',
            self::Overig => 'Overig',
        };
    }

    /**
     * Welke categorieën bij elkaar horen in de documentenbibliotheek. Zo zoek je
     * niet in twaalf losse mappen maar in vier logische groepen.
     */
    public function group(): string
    {
        return match ($this) {
            self::Offerte, self::Contract, self::Voorwaarden => 'Commercieel',
            self::Tekening, self::Technisch, self::Vergunning, self::Calculatie => 'Technisch',
            self::Veiligheid, self::Keuring => 'Veiligheid en keuring',
            self::Oplevering, self::Garantie => 'Oplevering en garantie',
            self::Factuur => 'Administratie',
            default => 'Overig',
        };
    }

    /**
     * De groepen in de volgorde waarin ze op het scherm staan.
     *
     * @return list<string>
     */
    public static function groups(): array
    {
        return ['Commercieel', 'Technisch', 'Veiligheid en keuring', 'Oplevering en garantie', 'Administratie', 'Overig'];
    }

    public function icon(): string
    {
        return match ($this) {
            self::Tekening => 'document-text',
            self::Foto => 'photo',
            self::Factuur => 'receipt',
            self::Veiligheid, self::Keuring => 'shield-check',
            self::Garantie => 'star',
            self::Vergunning => 'key',
            self::Contract, self::Voorwaarden => 'scale',
            default => 'paper-clip',
        };
    }
}
