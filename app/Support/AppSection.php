<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Indeling van de iOS/Android-app (prototype Renovion OS): secties met een eigen kleur, onderstreepte
 * tabs per sectie en paginakoppen. Bevat alleen schermen die echt bestaan.
 */
class AppSection
{
    /** @var array<string, array{label: string, icon: string, routes: list<string>}> */
    private const SECTIONS = [
        'vandaag' => ['label' => 'Vandaag', 'icon' => 'sun', 'routes' => ['dashboard']],
        'inbox' => ['label' => 'Inbox', 'icon' => 'exclamation-triangle', 'routes' => ['attention.*']],
        'meldingen' => ['label' => 'Meldingen', 'icon' => 'bell', 'routes' => ['notifications.*']],
        'teamchat' => ['label' => 'Teamchat', 'icon' => 'chat-bubble', 'routes' => ['chat.*']],
        'verkoop' => ['label' => 'Verkoop', 'icon' => 'funnel', 'routes' => ['leads.*', 'customers.*']],
        'offertes' => ['label' => 'Calculatie & offertes', 'icon' => 'document-text', 'routes' => ['quotes.*', 'calculations.*', 'price-items.*']],
        'projecten' => ['label' => 'Projecten', 'icon' => 'folder', 'routes' => ['projects.*', 'work-packages.*', 'delivery-reports.*']],
        'uitvoering' => ['label' => 'Planning & uitvoering', 'icon' => 'calendar', 'routes' => ['planning.*', 'tasks.*']],
        'mensen' => ['label' => 'Mensen & partners', 'icon' => 'users', 'routes' => ['team.*']],
        'organisatie' => ['label' => 'Organisatie', 'icon' => 'cog', 'routes' => ['automations.*', 'notification-settings.*']],
    ];

    /** Paginakop per scherm: normaal stuk, deel in de sectiekleur. @var array<string, array{string, string}> */
    private const HEADINGS = [
        'attention.index' => ['', 'Inbox'],
        'notifications.index' => ['', 'Meldingen'],
        'notification-settings.edit' => ['', 'Instellingen'],
        'projects.index' => ['Project', 'dossiers'],
        'leads.index' => ['Aanvragen & ', 'sales'],
        'customers.index' => ['Klanten & ', 'CRM'],
        'tasks.index' => ['Taken & ', 'werkstroom'],
    ];

    /** @return array{key: string, label: string, icon: string}|null */
    public static function current(): ?array
    {
        foreach (self::SECTIONS as $key => $section) {
            if (request()->routeIs(...$section['routes'])) {
                return ['key' => $key, 'label' => $section['label'], 'icon' => $section['icon']];
            }
        }

        return null;
    }

    /**
     * Tabs bovenaan een overzichtsscherm, zodat schermen uit één sectie naast elkaar liggen.
     *
     * @return list<array{label: string, route: string, active: bool}>
     */
    public static function tabs(): array
    {
        $tabs = match (self::current()['key'] ?? null) {
            'verkoop' => [
                ['Aanvragen', 'leads.index'],
                ['Klanten', 'customers.index'],
            ],
            'offertes' => [
                config('renovion.modules.quotes') ? ['Offertes', 'quotes.index'] : null,
                ['Calculaties', 'calculations.index'],
                ['Prijslijst', 'price-items.index'],
            ],
            'uitvoering' => [
                ['Planning', 'planning.index'],
                ['Taken', 'tasks.index'],
            ],
            'organisatie' => [
                config('renovion.modules.automations') && Gate::allows('manage-crm') ? ['Automations', 'automations.index'] : null,
                ['Instellingen', 'notification-settings.edit'],
            ],
            default => [],
        };

        $tabs = array_values(array_map(fn (array $tab) => [
            'label' => $tab[0],
            'route' => $tab[1],
            'active' => request()->routeIs($tab[1]),
        ], array_filter($tabs)));

        // Alleen op de overzichten zelf (een detailscherm heeft een eigen kop en terugweg), en niet bij één tab.
        return count($tabs) > 1 && collect($tabs)->contains('active', true) ? $tabs : [];
    }

    /** Korte uitleg onder de kop van een overzicht, zoals in het prototype; null = de eigen subkop van de pagina. */
    public static function subtitle(): ?string
    {
        return match (request()->route()?->getName()) {
            'projects.index' => 'Elk project is één dossier, van aanvraag tot nazorg.',
            'leads.index' => 'Elke aanvraag, van eerste contact tot project.',
            'quotes.index' => 'Elke offerte wordt een vastgezette versie zodra hij verstuurd is.',
            'calculations.index' => 'Elke calculatie wordt met één actie een offerte.',
            'planning.index' => Gate::allows('manage-crm') ? 'Dagoverzicht per persoon. Tik op een blok voor de details.' : 'Jouw planning vanaf vandaag, met adressen.',
            'tasks.index' => 'Van actie tot oplevering. Tik het rondje om een taak af te ronden.',
            default => null,
        };
    }

    /**
     * Kop van een pagina, verdeeld in een normaal stuk en een stuk in de sectiekleur.
     * Alleen overzichten krijgen die kleur: in een klant- of projectnaam zou hij willekeurig zijn.
     *
     * @return array{string, string}
     */
    public static function heading(string $title): array
    {
        $route = (string) request()->route()?->getName();

        if (isset(self::HEADINGS[$route])) {
            return self::HEADINGS[$route];
        }

        if (! str_ends_with($route, '.index')) {
            return [$title, ''];
        }

        return Str::contains($title, ' ')
            ? [Str::beforeLast($title, ' ').' ', Str::afterLast($title, ' ')]
            : ['', $title];
    }
}
