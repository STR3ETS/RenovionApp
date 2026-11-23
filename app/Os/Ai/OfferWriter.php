<?php

namespace App\Os\Ai;

use Anthropic\Client;
use App\Models\User;

/**
 * Nova schrijft mee aan een offerteblok: een nieuw blok, iets toevoegen,
 * korter, formeler of persoonlijker. Het resultaat is een voorstel; de
 * gebruiker past het toe of niet.
 */
final class OfferWriter
{
    public const MODES = ['nieuw', 'add', 'korter', 'formeler', 'persoonlijker'];

    /** @return array{txt: string, title: string, what: string} */
    public function write(User $user, string $projectId, string $mode, string $instruction, string $current, string $blockName): array
    {
        if (! Claude::enabled()) {
            throw new AiUnavailable('Nova heeft nog geen AI-sleutel (ANTHROPIC_API_KEY).');
        }
        $state = new StateReader($user);
        $p = $state->project($projectId);
        if (! $p) {
            throw new AiUnavailable('Dit project is niet gevonden.');
        }
        $c = $state->client($p['clientId'] ?? null) ?? [];
        $task = match ($mode) {
            'nieuw' => "Schrijf een nieuw offerteblok over: {$instruction}",
            'add' => "Voeg aan de huidige tekst toe: {$instruction}. Laat de bestaande tekst staan en zet de toevoeging er logisch bij.",
            'korter' => 'Maak de huidige tekst korter en krachtiger, behoud alle afspraken en feiten.',
            'formeler' => 'Maak de huidige tekst zakelijker en formeler, in de u-vorm.',
            default => 'Maak de huidige tekst persoonlijker voor deze klant, met wat de klant in de intake vertelde. Verzin geen nieuwe feiten.',
        };
        $context = json_encode([
            'project' => $p['naam'] ?? '', 'soort_werk' => $p['type'] ?? '', 'adres' => trim(($p['adres'] ?? '').', '.($p['plaats'] ?? ''), ', '),
            'klant' => $c['naam'] ?? '', 'contactpersoon' => $c['contact'] ?? '', 'intake' => $p['intake'] ?? [],
            'projectleider' => $state->personName($p['leider'] ?? null), 'start' => $p['start'] ?? null, 'oplevering' => $p['eind'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        $schema = [
            'type' => 'object',
            'properties' => [
                'tekst' => ['type' => 'string', 'description' => 'De nieuwe tekst van het blok. Alinea\'s gescheiden door een lege regel, opsommingen als regels die beginnen met "• ".'],
                'titel' => ['type' => 'string', 'description' => 'Korte titel van het blok (2 tot 4 woorden)'],
                'wat' => ['type' => 'string', 'description' => 'In één korte zin wat je hebt gedaan'],
            ],
            'required' => ['tekst', 'titel', 'wat'],
            'additionalProperties' => false,
        ];

        try {
            $message = (new Client(apiKey: (string) config('services.anthropic.key')))->messages->create(
                model: Claude::model(),
                maxTokens: 4000,
                system: 'Je schrijft offerteteksten voor Renovion Groep, een renovatiebedrijf in Arnhem. Schrijf in het Nederlands, in de u-vorm, warm maar zakelijk, concreet en zonder grote woorden. Gebruik alleen feiten uit de projectgegevens en de huidige tekst; verzin geen prijzen, datums, garantietermijnen of merken. Geen middelpunt (·) en geen lange gedachtestreepjes.',
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                messages: [['role' => 'user', 'content' => "Blok: {$blockName}\nProjectgegevens: {$context}\n\nHuidige tekst:\n".($current !== '' ? $current : '(leeg)')."\n\nOpdracht: {$task}"]],
            );
        } catch (\Throwable $e) {
            report($e);
            throw new AiUnavailable('Nova kon nu niet schrijven. Probeer het zo opnieuw.', previous: $e);
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text' && is_array($data = json_decode($block->text, true))) {
                return ['txt' => trim((string) $data['tekst']), 'title' => trim((string) $data['titel']), 'what' => trim((string) $data['wat'])];
            }
        }

        throw new AiUnavailable('Nova kon nu niet schrijven. Probeer het zo opnieuw.');
    }
}
