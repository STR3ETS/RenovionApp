<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AutomationRun;
use App\Models\AutomationSetting;
use App\Models\Calculation;
use App\Models\Lead;
use App\Models\Photo;
use App\Models\PriceItem;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Aan de slag": de uitleg van het systeem plus de checklist van wat er moet
 * staan voordat het team er echt mee werkt. De status wordt live berekend uit
 * de instellingen en de gegevens, niets wordt handmatig afgevinkt.
 */
class OnboardingGuide
{
    /**
     * @return Collection<int, array{key: string, title: string, description: string, done: bool, optional: bool, url: string|null, action: string|null, note: string|null}>
     */
    public function items(User $user): Collection
    {
        $aiReady = filled(config('renovion.ai.api_key'));
        $intakeReady = filled(config('renovion.lead_intake_token'));
        $mailReady = ! in_array(config('mail.default'), ['log', 'array', null], true);
        $schedulerRan = AutomationRun::query()->exists();
        $quotes = (bool) config('renovion.modules.quotes');
        $automations = (bool) config('renovion.modules.automations');

        return collect([
            $this->item(
                key: 'team',
                title: 'Teamaccounts controleren',
                description: 'Iedereen die met het systeem werkt heeft een eigen account met een rol: Admin, Sales, Projectleider, Werkvoorbereider of Uitvoerder. De rol bepaalt wat iemand ziet. Uitvoerders zien alleen hun eigen projecten, taken en planning.',
                done: User::query()->whereNot('role', UserRole::Klant)->count() >= 2,
                url: $user->can('manage-team') ? route('team.index') : null,
                action: 'Naar teambeheer',
                note: $user->can('manage-team') ? null : 'Alleen een admin kan accounts aanmaken.',
            ),
            $this->item(
                key: 'price_list',
                title: 'Prijslijst nalopen',
                description: 'De kostendatabase met arbeid, materiaal, materieel en onderaanneming is gevuld met praktijkprijzen. Calculaties en offerteposten komen hieruit. Klopt een prijs niet? Pas hem hier aan, dan rekent elke volgende calculatie ermee.',
                done: PriceItem::query()->active()->exists(),
                url: route('price-items.index'),
                action: 'Naar de prijslijst',
            ),
            $this->item(
                key: 'ai',
                title: 'Nova koppelen',
                description: $aiReady
                    ? 'Nova is gekoppeld. Je kunt taken, afspraken en notities inspreken, calculatieregels laten voorstellen en offerteteksten laten schrijven. Nova stelt voor, jij bevestigt.'
                    : 'Zonder koppeling werkt Nova niet: geen dagbriefing, geen spraakopdrachten en geen tekst- of calculatievoorstellen. Eazyonline zet de sleutel in de serverinstellingen.',
                done: $aiReady,
                note: $aiReady ? null : 'Regelt Eazyonline.',
            ),
            $this->item(
                key: 'website',
                title: 'Website koppelen',
                description: $intakeReady
                    ? 'Het contactformulier van renovion.nl levert aanvragen rechtstreeks aan. Een nieuwe aanvraag staat direct bij Aanvragen & Sales met de status Nieuw.'
                    : 'Zodra de website is gekoppeld, komt elke formulierverzending automatisch binnen als aanvraag. Tot die tijd voer je aanvragen zelf in.',
                done: $intakeReady,
                url: route('docs.api'),
                action: 'Bekijk de koppeling',
                note: $intakeReady ? null : 'Regelt Eazyonline samen met de websitebouwer.',
            ),
            $this->item(
                key: 'mail',
                title: 'E-mail verzenden aanzetten',
                description: $mailReady
                    ? 'Mails gaan echt de deur uit, bijvoorbeeld de offertelink naar de klant.'
                    : 'Op dit moment worden mails alleen in het logboek geschreven. Offertes deel je nu via de klantlink (kopiëren en zelf versturen). Eazyonline zet de mailserver goed.',
                done: $mailReady,
                note: $mailReady ? null : 'Regelt Eazyonline.',
            ),
            $automations ? $this->item(
                key: 'scheduler',
                title: 'Automations laten draaien',
                description: $schedulerRan
                    ? 'De automations draaien elke 15 minuten op de server. Nova signaleert stille aanvragen, offertes zonder reactie, naderende deadlines en meer.'
                    : 'De automations hebben nog niet gedraaid. Op de server moet de planner (cron) staan; daarna checkt het systeem elke 15 minuten wat aandacht vraagt.',
                done: $schedulerRan,
                url: route('automations.index'),
                action: 'Naar automations',
                note: $schedulerRan ? null : 'Regelt Eazyonline op de server.',
            ) : null,
            $automations ? $this->item(
                key: 'automations',
                title: 'Automations instellen',
                description: 'Per regel kies je: uit, alleen signaleren, eerst bevestigen of automatisch uitvoeren. Standaard signaleert Nova in het teamkanaal Algemeen. Loop ze één keer na en zet aan wat jullie zonder tussenkomst willen laten lopen.',
                done: AutomationSetting::query()->exists(),
                optional: true,
                url: route('automations.index'),
                action: 'Regels bekijken',
                note: $user->can('manage-team') ? null : 'Alleen een admin kan de modus wijzigen.',
            ) : null,
            $this->item(
                key: 'first_lead',
                title: 'Eerste aanvraag invoeren',
                description: 'Alles begint bij een aanvraag. Voer er een in (of wacht op de website), kwalificeer hem als koud, warm of heet en log elk contactmoment. Zo zie je vanaf dag één wat er speelt.',
                done: Lead::query()->exists(),
                url: route('leads.create'),
                action: 'Nieuwe aanvraag',
            ),
            $this->item(
                key: 'first_calculation',
                title: 'Eerste calculatie maken',
                description: 'Bouw een calculatie uit de prijslijst, typ of spreek de werkzaamheden in en laat Nova regels voorstellen. Subtotaal, onvoorzien, marge en btw worden berekend. Definitief maken zet de regels op slot.',
                done: Calculation::query()->exists(),
                url: route('calculations.create'),
                action: 'Nieuwe calculatie',
            ),
            $quotes ? $this->item(
                key: 'first_quote',
                title: 'Eerste offerte versturen',
                description: 'Maak met één actie een offerte uit de calculatie, werk hem af in de builder en verstuur hem. De klant opent een persoonlijke link, tekent digitaal of vraagt een aanpassing. Bij akkoord ontstaat automatisch een project.',
                done: Quote::query()->whereNotNull('sent_at')->exists(),
                url: route('quotes.index'),
                action: 'Naar offertes',
            ) : null,
            $this->item(
                key: 'first_photo',
                title: 'Eerste foto-bewijs uploaden',
                description: 'Op het werk vinkt de uitvoerder checklistitems af en maakt foto\'s met de telefoon. Een item dat foto\'s vereist kan niet af zonder bewijs. Probeer het één keer zelf op een testproject.',
                done: Photo::query()->exists(),
                optional: true,
                url: route('projects.index'),
                action: 'Naar projecten',
            ),
            $this->item(
                key: 'portal',
                title: 'Klantportaal uitproberen',
                description: 'Maak vanaf een klantpagina een portaal-account aan. De klant ziet voortgang, planning, foto\'s en wat er van hem wordt verwacht, en tekent fasen en het opleverrapport digitaal.',
                done: User::query()->where('role', UserRole::Klant)->exists(),
                optional: true,
                url: route('customers.index'),
                action: 'Naar klanten',
            ),
        ])->filter()->values();
    }

    /**
     * @return array{done: int, total: int, open: int, complete: bool}
     */
    public function progress(User $user): array
    {
        $required = $this->items($user)->where('optional', false);
        $done = $required->where('done', true)->count();

        return [
            'done' => $done,
            'total' => $required->count(),
            'open' => $required->count() - $done,
            'complete' => $done === $required->count(),
        ];
    }

    /**
     * Compacte status voor menu en dashboard. Uitvoerders hebben geen
     * checklist (die gaat over instellingen en CRM), wel de uitleg.
     *
     * @return array{dismissed: bool, open: int, done: int, total: int, complete: bool, checklist: bool}
     */
    public function summaryFor(User $user): array
    {
        $dismissed = $this->isDismissedFor($user);
        $checklist = $user->can('manage-crm');

        if ($dismissed || ! $checklist) {
            return ['dismissed' => $dismissed, 'open' => 0, 'done' => 0, 'total' => 0, 'complete' => true, 'checklist' => $checklist];
        }

        return ['dismissed' => false, 'checklist' => true, ...$this->progress($user)];
    }

    public function isDismissedFor(User $user): bool
    {
        return $user->onboarding_dismissed_at !== null;
    }

    public function dismiss(User $user): void
    {
        $user->forceFill(['onboarding_dismissed_at' => now()])->save();
    }

    public function restore(User $user): void
    {
        $user->forceFill(['onboarding_dismissed_at' => null])->save();
    }

    /**
     * @return array{key: string, title: string, description: string, done: bool, optional: bool, url: string|null, action: string|null, note: string|null}
     */
    private function item(string $key, string $title, string $description, bool $done, bool $optional = false, ?string $url = null, ?string $action = null, ?string $note = null): array
    {
        return compact('key', 'title', 'description', 'done', 'optional', 'url', 'action', 'note');
    }
}
