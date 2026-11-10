<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Eén bron voor de informatiearchitectuur van Renovion OS: secties in de sidebar,
 * tabbladen binnen een sectie en de rechtenmatrix per rol.
 *
 * Een sectie staat in een groep (Werkdag / Werk / Bedrijf) en bundelt een paar
 * verwante schermen. De sidebar toont de sectie, binnen het scherm staan de
 * tabbladen. Een item verschijnt pas als de route bestaat, zodat modules die nog
 * in de bouw zijn geen dood menu-item achterlaten.
 */
class Navigation
{
    public const GROUPS = ['Werkdag', 'Werk', 'Bedrijf'];

    /** Modules die alleen directie en superadmin inrichten. */
    public const MANAGEMENT_ONLY = ['teambeheer', 'instellingen', 'mailbuilder', 'automations', 'skills'];

    /** Werkdagmodules die iedereen met een interne rol heeft. */
    private const WORKDAY = ['vandaag', 'inbox', 'teamchat'];

    /**
     * Secties: id => groep, label, icoon, teller en de schermen erin.
     * Items: modulekey => [label, routenaam].
     *
     * @return array<string, array<string, mixed>>
     */
    public static function sections(): array
    {
        return [
            'vandaag' => [
                'group' => 'Werkdag', 'label' => 'Vandaag', 'icon' => 'sun',
                'items' => ['vandaag' => ['Vandaag', 'dashboard']],
            ],
            'inbox' => [
                'group' => 'Werkdag', 'label' => 'Inbox', 'icon' => 'exclamation-triangle', 'counter' => 'inbox',
                'items' => ['inbox' => ['Inbox', 'attention.index']],
            ],
            'teamchat' => [
                'group' => 'Werkdag', 'label' => 'Teamchat', 'icon' => 'chat-bubble-left-right', 'counter' => 'chat',
                'items' => ['teamchat' => ['Teamchat', 'chat.index']],
            ],
            'agents' => [
                'group' => 'Werkdag', 'label' => 'Nova en agents', 'icon' => 'sparkles',
                'items' => [
                    'agents' => ['Uitvoeringen', 'agents.index'],
                    'goedkeuringen' => ['Goedkeuringen', 'approvals.index'],
                    'audit' => ['Audit', 'audit.index'],
                    'skills' => ['Skills en SOPs', 'skills.index'],
                ],
            ],
            'verkoop' => [
                'group' => 'Werk', 'label' => 'Verkoop', 'icon' => 'funnel',
                'items' => [
                    'sales' => ['Aanvragen', 'leads.index'],
                    'opvolging' => ['Opvolging', 'follow-ups.index'],
                    'klanten' => ['Klanten', 'customers.index'],
                    'intake' => ['Intakes en opnames', 'intakes.index'],
                ],
            ],
            'offertes' => [
                'group' => 'Werk', 'label' => 'Calculatie en offertes', 'icon' => 'document-text',
                'items' => [
                    'offertedash' => ['Dashboard', 'quote-dashboard'],
                    'offertes' => ['Offertes', 'quotes.index'],
                    'calculaties' => ['Calculaties', 'calculations.index'],
                    'begrotingen' => ['Begrotingen', 'budgets.index'],
                    'prijslijst' => ['Prijslijst', 'price-items.index'],
                ],
            ],
            'projecten' => [
                'group' => 'Werk', 'label' => 'Projecten', 'icon' => 'folder',
                'items' => [
                    'projecten' => ['Dossiers', 'projects.index'],
                    'portfolio' => ['Portfolio', 'portfolio'],
                    'kwaliteit' => ['Kwaliteit en oplevering', 'quality.index'],
                    'nazorg' => ['Nazorg en service', 'service.index'],
                    'servicehub' => ['Klantportaal', 'portal-preview.index'],
                ],
            ],
            'uitvoering' => [
                'group' => 'Werk', 'label' => 'Planning en uitvoering', 'icon' => 'calendar',
                'items' => [
                    'planning' => ['Planning', 'planning.index'],
                    'klussen' => ['Mijn klussen', 'jobs.index'],
                    'taken' => ['Taken', 'tasks.index'],
                    'werkbonnen' => ['Werkbonnen', 'work-orders.index'],
                ],
            ],
            'mensen' => [
                'group' => 'Bedrijf', 'label' => 'Mensen en partners', 'icon' => 'identification',
                'items' => [
                    'workforce' => ['Eigen mensen', 'workforce.index'],
                    'onderaannemers' => ['Onderaannemers', 'subcontractors.index'],
                    'werkhub' => ['Prijsaanvragen', 'rfqs.index'],
                ],
            ],
            'geld' => [
                'group' => 'Bedrijf', 'label' => 'Geld', 'icon' => 'banknotes',
                'items' => [
                    'financieel' => ['Financieel', 'finance.index'],
                    'facturatie' => ['Facturatie', 'invoices.index'],
                    'inkoop' => ['Inkoop', 'purchase-orders.index'],
                    'nacalculatie' => ['Nacalculatie', 'cost-reviews.index'],
                ],
            ],
            'inzicht' => [
                'group' => 'Bedrijf', 'label' => 'Rapportages', 'icon' => 'chart-bar',
                'items' => [
                    'rapportages' => ['Rapportages', 'reports.index'],
                    'marketing' => ['Marketing', 'marketing.index'],
                ],
            ],
            'organisatie' => [
                'group' => 'Bedrijf', 'label' => 'Organisatie', 'icon' => 'cog-6-tooth',
                'items' => [
                    'teambeheer' => ['Team en rechten', 'team.index'],
                    'automations' => ['Automations', 'automations.index'],
                    'mailbuilder' => ['Mailbuilder', 'mail-templates.index'],
                    'documenten' => ['Documenten', 'documents.index'],
                    'instellingen' => ['Instellingen', 'settings.index'],
                ],
            ],
            'academy' => [
                'group' => 'Bedrijf', 'label' => 'Academy', 'icon' => 'academic-cap',
                'items' => ['academy' => ['Academy', 'academy.index']],
            ],
        ];
    }

    /**
     * De rechtenmatrix uit de briefing: per rol de modules die zij zien.
     *
     * @return array<string, list<string>|string>
     */
    public static function permissions(): array
    {
        return [
            UserRole::Admin->value => '*',
            UserRole::Directie->value => '*',
            UserRole::Sales->value => [
                ...self::WORKDAY, 'sales', 'opvolging', 'klanten', 'intake', 'calculaties',
                'offertes', 'offertedash', 'projecten', 'taken', 'planning', 'servicehub',
                'documenten', 'marketing', 'academy',
            ],
            UserRole::Calculator->value => [
                ...self::WORKDAY, 'calculaties', 'begrotingen', 'prijslijst', 'offertes', 'offertedash',
                'intake', 'projecten', 'taken', 'inkoop', 'werkhub', 'documenten', 'academy',
            ],
            UserRole::Projectleider->value => [
                ...self::WORKDAY, 'projecten', 'portfolio', 'planning', 'klussen', 'taken', 'werkbonnen',
                'kwaliteit', 'nazorg', 'servicehub', 'workforce', 'onderaannemers', 'werkhub', 'inkoop',
                'documenten', 'financieel', 'nacalculatie', 'rapportages', 'klanten', 'academy', 'agents',
                'goedkeuringen', 'audit',
            ],
            UserRole::Werkvoorbereider->value => [
                ...self::WORKDAY, 'projecten', 'planning', 'klussen', 'taken', 'werkbonnen', 'inkoop',
                'werkhub', 'onderaannemers', 'prijslijst', 'begrotingen', 'documenten', 'kwaliteit', 'academy',
            ],
            UserRole::Inkoper->value => [
                ...self::WORKDAY, 'inkoop', 'werkhub', 'onderaannemers', 'prijslijst', 'projecten',
                'documenten', 'academy',
            ],
            UserRole::Administratie->value => [
                ...self::WORKDAY, 'facturatie', 'financieel', 'nacalculatie', 'klanten', 'documenten',
                'rapportages', 'inkoop', 'projecten', 'academy',
            ],
            UserRole::Uitvoerder->value => [
                'vandaag', 'klussen', 'planning', 'taken', 'werkbonnen', 'kwaliteit', 'projecten',
                'teamchat', 'academy',
            ],
            UserRole::Onderaannemer->value => ['partner', 'klussen', 'werkbonnen'],
            UserRole::Klant->value => ['portaal'],
        ];
    }

    /**
     * De mobiele bottom-nav: per module een kort label en icoon, en per rol de
     * schermen waar die rol vanaf de telefoon daadwerkelijk heen gaat.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    private const BOTTOM_NAV = [
        'vandaag' => ['Vandaag', 'home', 'dashboard'],
        'klussen' => ['Klussen', 'identification', 'jobs.index'],
        'werkbonnen' => ['Werkbonnen', 'wrench', 'work-orders.index'],
        'projecten' => ['Projecten', 'building-office', 'projects.index'],
        'planning' => ['Planning', 'calendar', 'planning.index'],
        'taken' => ['Taken', 'clipboard-check', 'tasks.index'],
        'teamchat' => ['Chat', 'chat-bubble', 'chat.index'],
        'sales' => ['Sales', 'funnel', 'leads.index'],
        'financieel' => ['Financieel', 'chart-bar', 'finance.index'],
        'inbox' => ['Inbox', 'exclamation-triangle', 'attention.index'],
    ];

    /** @var array<string, list<string>> */
    private const BOTTOM_NAV_ROLES = [
        UserRole::Uitvoerder->value => ['vandaag', 'klussen', 'werkbonnen', 'planning', 'taken'],
        UserRole::Sales->value => ['vandaag', 'sales', 'projecten', 'taken'],
        UserRole::Administratie->value => ['vandaag', 'financieel', 'projecten', 'teamchat'],
        UserRole::Inkoper->value => ['vandaag', 'inbox', 'projecten', 'teamchat'],
    ];

    private const BOTTOM_NAV_DEFAULT = ['vandaag', 'inbox', 'projecten', 'planning', 'teamchat'];

    /**
     * @return list<array{label: string, icon: string, url: string, active: bool}>
     */
    public static function bottomNavFor(User $user): array
    {
        $keys = self::BOTTOM_NAV_ROLES[$user->role->value] ?? self::BOTTOM_NAV_DEFAULT;
        $out = [];

        foreach ($keys as $key) {
            [$label, $icon, $route] = self::BOTTOM_NAV[$key];

            if (! Route::has($route) || ! self::allows($user->role, $key)) {
                continue;
            }

            $out[] = [
                'label' => $label,
                'icon' => $icon,
                'url' => route($route),
                'active' => request()->routeIs($route) || self::childOf($route),
            ];
        }

        return $out;
    }

    /** Mag deze rol bij deze module? */
    public static function allows(UserRole $role, string $module): bool
    {
        if (in_array($module, self::MANAGEMENT_ONLY, true)) {
            return $role->isManagement();
        }

        $perms = self::permissions()[$role->value] ?? [];

        return $perms === '*' || in_array($module, (array) $perms, true);
    }

    /**
     * De sidebar voor deze gebruiker: alleen secties met minstens één toegestaan,
     * bestaand scherm. Het eerste toegestane scherm is het doel van de sectie.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function forUser(User $user): array
    {
        $groups = [];

        foreach (self::sections() as $id => $section) {
            $items = self::itemsFor($user, $section['items']);

            if ($items === []) {
                continue;
            }

            $groups[$section['group']][] = [
                'id' => $id,
                'label' => count($items) === 1 ? $items[0]['label'] : $section['label'],
                'icon' => $section['icon'],
                'url' => $items[0]['url'],
                'counter' => $section['counter'] ?? null,
                'active' => collect($items)->contains('active', true),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * De tabbladen van de sectie waar de gebruiker nu staat. Leeg als die sectie
     * maar één scherm heeft.
     *
     * @return list<array<string, mixed>>
     */
    public static function tabsFor(User $user): array
    {
        foreach (self::forUser($user) as $sections) {
            foreach ($sections as $section) {
                if ($section['active'] && count($section['items']) > 1) {
                    return $section['items'];
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $items
     * @return list<array<string, mixed>>
     */
    private static function itemsFor(User $user, array $items): array
    {
        $out = [];

        foreach ($items as $key => [$label, $route]) {
            if (! Route::has($route) || ! self::allows($user->role, $key)) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $label,
                'url' => route($route),
                'active' => request()->routeIs($route) || self::childOf($route),
            ];
        }

        return $out;
    }

    /**
     * Detailschermen horen bij hun lijst: offertes.show valt onder offertes.index.
     */
    private static function childOf(string $route): bool
    {
        return str_ends_with($route, '.index')
            && request()->routeIs(str_replace('.index', '.*', $route));
    }
}
