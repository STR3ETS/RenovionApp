<?php

namespace App\Os\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;

/**
 * De verbinding met Claude voor Nova en de agents, en de werkcyclus die
 * beide gebruiken: Claude leest met de leesfuncties in de echte gegevens,
 * doet voorstellen (die eerst worden gecontroleerd) en sluit af met een
 * antwoord in gewone taal.
 */
final class Claude
{
    /** Modellen die de server-side terugval bij een weigering ondersteunen. */
    private const FALLBACK_MODELS = ['claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5', 'claude-fable-5-2', 'claude-fable-5-1'];

    /** Prijs per miljoen tokens (invoer, uitvoer), om de kosten per uitvoering te tonen. */
    private const PRICES = [
        'claude-opus-5-5' => [4, 20], 'claude-opus-5' => [5, 25], 'claude-sonnet-5-5' => [2, 10], 'claude-sonnet-5' => [2, 10],
        'claude-haiku-5-5' => [0.1, 0.5], 'claude-fable-5-2' => [8, 40],
    ];

    public static function enabled(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    public static function model(): string
    {
        return (string) config('services.anthropic.model', 'claude-opus-5-5');
    }

    /**
     * Laat Claude werken tot er een antwoord is.
     *
     * @return array{tekst: string, voorstellen: list<array<string, mixed>>, kosten: float, stappen: list<string>}
     */
    public function run(string $system, array $messages, ReadTools $read, Actions $actions, string $effort = 'medium', int $maxSteps = 8, string $proposalTool = 'voorstel'): array
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'));
        $model = self::model();
        $tools = [...ReadTools::definitions(), Actions::toolDefinition($proposalTool)];
        $proposals = [];
        $steps = [];
        $cost = 0.0;
        $text = '';
        $said = [];

        for ($i = 0; $i < $maxSteps; $i++) {
            $params = [
                'model' => $model,
                'maxTokens' => 8000,
                'system' => [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                'messages' => $messages,
                'tools' => $tools,
                'thinking' => ['type' => 'adaptive'],
                'outputConfig' => ['effort' => $effort],
            ];
            if (in_array($model, self::FALLBACK_MODELS, true)) {
                $params['betas'] = ['server-side-fallback-2026-07-01'];
                $params['fallbacks'] = 'default';
            }

            try {
                $response = $client->beta->messages->create(...$params);
            } catch (APIStatusException $e) {
                throw new AiUnavailable(match ((int) $e->status) {
                    401, 403 => 'De AI-sleutel wordt niet geaccepteerd. Controleer ANTHROPIC_API_KEY.',
                    429 => 'Nova is even te druk bevraagd. Probeer het over een minuut opnieuw.',
                    default => 'Nova kon de AI niet bereiken ('.$e->status.'). Probeer het zo opnieuw.',
                }, previous: $e);
            } catch (\Throwable $e) {
                throw new AiUnavailable('Nova kon de AI niet bereiken. Controleer de internetverbinding van de server.', previous: $e);
            }

            [$in, $out] = self::PRICES[$model] ?? [4, 20];
            $cost += ($response->usage->inputTokens * $in + $response->usage->outputTokens * $out) / 1_000_000;

            if ($response->stopReason === 'refusal') {
                $text = 'Dit verzoek kan ik niet uitvoeren.';
                break;
            }

            $results = [];
            $texts = [];
            foreach ($response->content as $block) {
                if ($block->type === 'text') {
                    $texts[] = $block->text;
                } elseif ($block->type === 'tool_use') {
                    $input = is_array($block->input) ? $block->input : (array) $block->input;
                    [$result, $isError] = $this->tool($block->name, $input, $read, $actions, $proposals, $proposalTool);
                    $steps[] = $block->name.($block->name === $proposalTool ? '' : ': '.mb_substr(json_encode($input, JSON_UNESCAPED_UNICODE), 0, 120));
                    $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => $result, 'isError' => $isError];
                }
            }

            // Tekst die Claude tussen de stappen door schrijft, hoort ook bij het antwoord.
            $said = [...$said, ...array_filter(array_map('trim', $texts))];
            if ($response->stopReason !== 'tool_use' || ! $results) {
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $messages[] = ['role' => 'user', 'content' => $results];
        }
        $text = $text ?: implode("\n\n", $said);

        return ['tekst' => $text, 'voorstellen' => $proposals, 'kosten' => round($cost, 4), 'stappen' => $steps];
    }

    /** @return array{0: string, 1: bool} */
    private function tool(string $name, array $input, ReadTools $read, Actions $actions, array &$proposals, string $proposalTool): array
    {
        if ($name !== $proposalTool) {
            try {
                return [$read->run($name, $input), false];
            } catch (\Throwable $e) {
                report($e);

                return ['Deze gegevens konden niet worden gelezen.', true];
            }
        }

        $list = array_values(array_filter((array) ($input['acties'] ?? []), 'is_array'));
        try {
            $lines = $actions->check($list);
        } catch (ActionException $e) {
            return ['Het voorstel klopt niet: '.$e->getMessage().'. Zoek het juiste gegeven op en doe het voorstel opnieuw.', true];
        }

        $proposals[] = [
            'id' => 'p'.bin2hex(random_bytes(6)),
            'begrepen' => (string) ($input['begrepen'] ?? ''),
            'data' => (string) ($input['gebruikte_gegevens'] ?? ''),
            'gevolg' => (string) ($input['gevolgen'] ?? ''),
            'changes' => $lines,
            'acties' => $list,
            'safe' => Actions::isSafe($list),
        ];

        return ['Het voorstel staat klaar voor de gebruiker. Het wordt pas uitgevoerd na bevestiging; zeg niet dat het al gebeurd is.', false];
    }
}
