<?php

namespace App\Services;

use Anthropic\Client;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Nova maakt van een transcript een verslag: samenvatting, besluiten, acties (met eigenaar en datum) en
 * open vragen. Het is een concept: de gebruiker kiest zelf welke acties taken worden.
 */
class MeetingReporter
{
    public function isConfigured(): bool
    {
        return filled(config('renovion.ai.api_key'));
    }

    /**
     * @return array{summary: string, decisions: list<string>, actions: list<array{title: string, owner_id: int|null, owner: string|null, due: string|null, task_id: null}>, questions: list<string>, speakers: array<string, string>}
     */
    public function report(Meeting $meeting): array
    {
        $team = User::where('role', '!=', UserRole::Klant)->orderBy('name')->get(['id', 'name']);
        $client = new Client(apiKey: (string) config('renovion.ai.api_key'));

        $response = $client->messages->create(
            model: (string) config('renovion.ai.model'),
            maxTokens: 4000,
            outputConfig: ['effort' => 'low'],
            system: [['type' => 'text', 'text' => $this->systemPrompt($meeting, $team->pluck('name')->all())]],
            tools: [$this->tool()],
            toolChoice: ['type' => 'tool', 'name' => 'meeting_report'],
            messages: [['role' => 'user', 'content' => $this->transcriptText($meeting)]],
        );

        foreach ($response->content as $block) {
            if ($block->type === 'tool_use' && $block->name === 'meeting_report') {
                return $this->normalize(json_decode(json_encode($block->input), true) ?: [], $team, $meeting->started_at);
            }
        }

        throw new RuntimeException('Nova gaf geen verslag terug.');
    }

    private function transcriptText(Meeting $meeting): string
    {
        return collect($meeting->transcript ?? [])
            ->map(fn (array $line) => $line['speaker'] === ''
                ? sprintf('[%s] %s', gmdate('H:i:s', intdiv($line['start'], 1000)), $line['text'])
                : sprintf('[%s] Spreker %s: %s', gmdate('H:i:s', intdiv($line['start'], 1000)), $line['speaker'], $line['text']))
            ->implode("\n");
    }

    /** @param  list<string>  $team */
    private function systemPrompt(Meeting $meeting, array $team): string
    {
        $context = collect([
            'Soort: '.$meeting->kind->label(),
            'Onderwerp: '.$meeting->title,
            $meeting->project ? 'Project: '.$meeting->project->name.' (klant '.$meeting->project->customer->name.')' : null,
            'Datum: '.$meeting->started_at->translatedFormat('l j F Y'),
            'Opgenomen door: '.($meeting->user?->name ?? 'onbekend'),
        ])->filter()->implode("\n");

        return <<<PROMPT
        Je bent Nova, de assistent van Renovion (renovatiebedrijf in Arnhem). Je krijgt het transcript van een
        opgenomen gesprek, soms met sprekers als letters (A, B, ...), soms zonder sprekers. Leid dan uit de
        inhoud af wie wat zegt, maar verzin geen namen. Maak er een zakelijk verslag van met de tool
        meeting_report.

        {$context}

        Teamleden van Renovion: {$this->list($team)}.

        Regels:
        - Schrijf in het Nederlands, kort en concreet. Gebruik geen middots en geen em-dashes; scheid met komma's.
        - Neem alleen op wat echt gezegd is. Verzin geen besluiten, bedragen of datums.
        - Besluit = iets waar men het over eens werd. Actie = iemand gaat iets doen. Open vraag = nog niet beantwoord.
        - Eigenaar van een actie: alleen een naam uit de teamlijst, en alleen als duidelijk is wie het doet; anders leeg.
          Is de klant aan zet, zet dat in de actietitel ("Klant: ...") en laat de eigenaar leeg.
        - Datum van een actie als YYYY-MM-DD, alleen als die genoemd is ("voor vrijdag" = de eerstvolgende vrijdag na de gespreksdatum).
        - Sprekers (alleen als er letters staan): geef per letter een naam als die duidelijk uit het gesprek blijkt (bijv. iemand wordt aangesproken), of "Klant". Anders weglaten.
        - Samenvatting: 2 tot 4 zinnen.
        PROMPT;
    }

    /** @param  list<string>  $items */
    private function list(array $items): string
    {
        return $items === [] ? 'onbekend' : implode(', ', $items);
    }

    /** @return array<string, mixed> */
    private function tool(): array
    {
        return [
            'name' => 'meeting_report',
            'description' => 'Het verslag van het gesprek.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string', 'description' => 'Samenvatting in 2 tot 4 zinnen.'],
                    'decisions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'actions' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'owner' => ['type' => ['string', 'null'], 'description' => 'Naam uit de teamlijst, of leeg.'],
                            'due' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD of leeg.'],
                        ],
                        'required' => ['title'],
                    ]],
                    'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'speakers' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => ['label' => ['type' => 'string'], 'name' => ['type' => 'string']],
                        'required' => ['label', 'name'],
                    ]],
                ],
                'required' => ['summary', 'decisions', 'actions', 'questions'],
            ],
        ];
    }

    /**
     * Wat Nova teruggeeft wordt nagelopen: alleen tekst, eigenaars die echt in het team zitten, geldige datums.
     *
     * @param  array<string, mixed>  $input
     * @param  Collection<int, User>  $team
     * @return array{summary: string, decisions: list<string>, actions: list<array{title: string, owner_id: int|null, owner: string|null, due: string|null, task_id: null}>, questions: list<string>, speakers: array<string, string>}
     */
    private function normalize(array $input, $team, Carbon $date): array
    {
        $strings = fn ($value) => collect(is_array($value) ? $value : [])->filter(fn ($item) => is_string($item) && trim($item) !== '')->map(fn ($item) => trim($item))->values()->all();
        $owner = function ($name) use ($team): ?User {
            if (! is_string($name) || trim($name) === '') {
                return null;
            }
            $name = mb_strtolower(trim($name));

            return $team->first(fn (User $user) => mb_strtolower($user->name) === $name)
                ?? $team->first(fn (User $user) => mb_strtolower(strtok($user->name, ' ')) === mb_strtolower(strtok($name, ' ')));
        };

        $actions = collect(is_array($input['actions'] ?? null) ? $input['actions'] : [])
            ->filter(fn ($action) => is_array($action) && is_string($action['title'] ?? null) && trim($action['title']) !== '')
            ->map(function (array $action) use ($owner, $date) {
                $user = $owner($action['owner'] ?? null);
                $due = is_string($action['due'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $action['due']) && Carbon::parse($action['due'])->gte($date->copy()->startOfDay())
                    ? $action['due']
                    : null;

                return ['title' => trim($action['title']), 'owner_id' => $user?->id, 'owner' => $user?->name, 'due' => $due, 'task_id' => null];
            })
            ->values()
            ->all();

        $speakers = collect(is_array($input['speakers'] ?? null) ? $input['speakers'] : [])
            ->filter(fn ($speaker) => is_array($speaker) && is_string($speaker['label'] ?? null) && is_string($speaker['name'] ?? null) && trim($speaker['name']) !== '')
            ->mapWithKeys(fn (array $speaker) => [strtoupper(trim($speaker['label'])) => trim($speaker['name'])])
            ->all();

        return [
            'summary' => trim((string) ($input['summary'] ?? '')),
            'decisions' => $strings($input['decisions'] ?? []),
            'actions' => $actions,
            'questions' => $strings($input['questions'] ?? []),
            'speakers' => $speakers,
        ];
    }
}
