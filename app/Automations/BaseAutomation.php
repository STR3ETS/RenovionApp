<?php

namespace App\Automations;

use App\Enums\AutomationMode;
use App\Models\AutomationSetting;
use App\Models\ChatChannel;
use App\Services\NovaAssistant;
use Closure;

/**
 * Gedeelde modus-afhandeling (briefing §12): per regel instelbaar of Nova
 * alleen signaleert, eerst om bevestiging vraagt (voorstel in de teamchat
 * met "Ja, doe maar") of de actie automatisch uitvoert.
 */
abstract class BaseAutomation implements Automation
{
    public function defaultMode(): AutomationMode
    {
        return AutomationMode::Automatisch;
    }

    public function mode(): AutomationMode
    {
        return AutomationSetting::modeFor($this->key(), $this->defaultMode());
    }

    /**
     * Voer één actie uit volgens de ingestelde modus.
     *
     * @param  string  $signal  Mensleesbare omschrijving van het signaal.
     * @param  Closure  $action  De echte actie (alleen in modus "automatisch").
     * @param  array{type: string, params: array<string, mixed>}|null  $novaAction  Voorstel voor de bevestigen-modus; zonder voorstel valt die terug op signaleren.
     * @param  string|null  $url  Optionele link bij het chatsignaal.
     */
    protected function act(string $signal, Closure $action, ?array $novaAction = null, ?string $url = null): void
    {
        match ($this->mode()) {
            AutomationMode::Automatisch => $action(),
            AutomationMode::Bevestigen => $this->postToChat($signal, $novaAction, $url),
            AutomationMode::Signaleren => $this->postToChat($signal, null, $url),
            AutomationMode::Uit => null,
        };
    }

    /**
     * Nova meldt het signaal (en eventueel een bevestigbaar voorstel) in het
     * teamkanaal "Algemeen" — dezelfde flow als @Nova in de chat.
     *
     * @param  array{type: string, params: array<string, mixed>}|null  $novaAction
     */
    private function postToChat(string $signal, ?array $novaAction, ?string $url): void
    {
        $channel = ChatChannel::firstOrCreate(['type' => 'team', 'name' => 'Algemeen']);

        $channel->messages()->create([
            'user_id' => null,
            'body' => $signal.($novaAction !== null ? ' Zal ik dit klaarzetten?' : ''),
            'nova' => match (true) {
                $novaAction !== null => [
                    'action' => $novaAction,
                    'preview' => app(NovaAssistant::class)->preview($novaAction),
                    'executed' => false,
                ],
                $url !== null => ['executed' => true, 'url' => $url],
                default => null,
            },
        ]);
    }
}
