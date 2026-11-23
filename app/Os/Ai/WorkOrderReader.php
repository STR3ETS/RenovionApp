<?php

namespace App\Os\Ai;

use Anthropic\Client;
use App\Models\User;

/**
 * Leest een foto of scan van een papieren werkbon met Claude en geeft de
 * velden terug. Het is altijd een concept: de gebruiker controleert alles
 * voordat de werkbon wordt opgeslagen.
 */
final class WorkOrderReader
{
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

    /** @return array<string, mixed> */
    public function read(User $user, string $mediaType, string $base64): array
    {
        if (! Claude::enabled()) {
            throw new AiUnavailable('Uitlezen kan pas als er een AI-sleutel is (ANTHROPIC_API_KEY). Vul de velden zelf in.');
        }
        if (! in_array($mediaType, self::TYPES, true)) {
            throw new AiUnavailable('Dit bestand kan niet worden gelezen. Gebruik een foto (jpg of png) of een pdf.');
        }

        $state = new StateReader($user);
        $projects = implode("\n", array_map(fn ($p) => $p['id'].': '.($p['naam'] ?? '').', '.($p['plaats'] ?? '').', klant '.(($state->client($p['clientId'] ?? null)['naam'] ?? '')), $state->list('projects')));
        $people = implode("\n", array_map(fn ($e) => $e['id'].': '.($e['naam'] ?? '').' ('.($e['rol'] ?? '').')', array_filter($state->list('emps'), fn ($e) => ($e['type'] ?? '') !== 'kantoor')));
        $today = now()->toDateString();

        $file = $mediaType === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $base64]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => $base64]];

        $string = ['type' => ['string', 'null']];
        $schema = [
            'type' => 'object',
            'properties' => [
                'titel' => ['type' => 'string', 'description' => 'Korte omschrijving van de werkzaamheden'],
                'project_id' => $string + ['description' => 'Id uit de lijst van projecten, of null als het niet duidelijk is'],
                'medewerker_id' => $string + ['description' => 'Id uit de lijst van vakmensen, of null'],
                'datum' => $string + ['description' => 'JJJJ-MM-DD'],
                'uren' => ['type' => ['number', 'null']],
                'opmerkingen' => ['type' => 'string'],
                'getekend' => ['type' => 'boolean', 'description' => 'Staat er een handtekening van de klant op?'],
                'materialen' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'omschrijving' => ['type' => 'string'], 'aantal' => ['type' => ['number', 'null']], 'eenheid' => ['type' => 'string'],
                ], 'required' => ['omschrijving', 'aantal', 'eenheid'], 'additionalProperties' => false]],
                'twijfel' => ['type' => 'string', 'description' => 'Wat slecht leesbaar of onzeker was, leeg als alles duidelijk is'],
            ],
            'required' => ['titel', 'project_id', 'medewerker_id', 'datum', 'uren', 'opmerkingen', 'getekend', 'materialen', 'twijfel'],
            'additionalProperties' => false,
        ];

        try {
            $message = (new Client(apiKey: (string) config('services.anthropic.key')))->messages->create(
                model: Claude::model(),
                maxTokens: 4000,
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                messages: [['role' => 'user', 'content' => [
                    $file,
                    ['type' => 'text', 'text' => "Dit is een papieren werkbon van Renovion Groep. Lees hem uit. Vandaag is {$today}. Kies het project en de vakman alleen uit deze lijsten; past niets, geef null.\n\nProjecten:\n{$projects}\n\nVakmensen:\n{$people}\n\nSchrijf in het Nederlands. Neem alleen over wat er echt staat; verzin niets."],
                ]]],
            );
        } catch (\Throwable $e) {
            report($e);
            throw new AiUnavailable('Uitlezen lukte niet. Vul de velden zelf in of probeer het opnieuw.', previous: $e);
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    // Alleen id's die echt bestaan
                    $data['project_id'] = $state->project($data['project_id'] ?? null) ? strtoupper((string) $data['project_id']) : null;
                    $data['medewerker_id'] = $state->person($data['medewerker_id'] ?? null) ? $data['medewerker_id'] : null;

                    return $data;
                }
            }
        }

        throw new AiUnavailable('De werkbon kon niet worden gelezen. Vul de velden zelf in.');
    }
}
