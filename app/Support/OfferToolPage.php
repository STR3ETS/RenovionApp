<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Levert de offertetool en zijn inlogpagina uit. Beide zijn kant-en-klare
 * HTML-bestanden in resources/offer-tool; de server zet er direct na <body>
 * alleen een instelling bij (window.RENOVION of window.RENOVION_LOGIN).
 *
 * Een nieuwe versie van de tool is dus: het bestand vervangen. De test
 * OfferToolTest bewaakt dat de ankers hieronder er dan nog in staan.
 */
final class OfferToolPage
{
    public const TOOL = 'offer-tool/tool.html';

    public const LOGIN = 'offer-tool/toegang.html';

    /**
     * De tool bewaart zijn concept in de browser onder één vaste naam. Met
     * meer dan één offerte op hetzelfde adres zou het concept van de ene
     * offerte in de andere terechtkomen, dus de naam krijgt het nummer erbij.
     *
     * @var array<string, string> anker => vervanging, met %s voor het nummer
     */
    public const PER_OFFER = [
        "const KEY='renovion-begroting-v2'" => "const KEY='renovion-begroting-v2-%s'",
        "indexedDB.open('renovion-begroting',1)" => "indexedDB.open('renovion-begroting-%s',1)",
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public static function tool(string $number, array $config): string
    {
        $html = self::read(self::TOOL);

        foreach (self::PER_OFFER as $anchor => $replacement) {
            if (substr_count($html, $anchor) !== 1) {
                Log::warning('Offertetool: anker niet gevonden, opslag in de browser is niet per offerte gescheiden.', ['anker' => $anchor]);

                continue;
            }

            $html = str_replace($anchor, sprintf($replacement, $number), $html);
        }

        return self::inject($html, 'RENOVION', $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function login(array $config): string
    {
        return self::inject(self::read(self::LOGIN), 'RENOVION_LOGIN', $config);
    }

    public static function read(string $file): string
    {
        $html = @file_get_contents(resource_path($file));

        if ($html === false) {
            throw new RuntimeException("De offertetool ontbreekt: resources/{$file}.");
        }

        return $html;
    }

    /**
     * Zet de instelling direct na <body>. De JSON kan tekst van gebruikers
     * bevatten, dus < > en & gaan als \u-code mee: een "</script>" in een
     * omschrijving kan het script zo niet afsluiten.
     *
     * @param  array<string, mixed>  $config
     */
    private static function inject(string $html, string $name, array $config): string
    {
        $position = strpos($html, '<body>');

        if ($position === false) {
            throw new RuntimeException('De offertetool heeft geen <body> waar de instelling achter kan.');
        }

        $json = json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return substr_replace($html, '<body><script>window.'.$name.'='.$json.'</script>', $position, strlen('<body>'));
    }
}
