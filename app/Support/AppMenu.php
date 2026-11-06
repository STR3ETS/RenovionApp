<?php

namespace App\Support;

use App\Models\User;

/**
 * Onderbalk, zijmenu en "+"-menu van de iOS/Android-app op één plek. De schil van de serverpagina's
 * (partials/app) en de schermen in de app zelf (mobile/app, via api/app/ik) lezen allebei hieruit,
 * zodat ze niet uit elkaar lopen.
 */
class AppMenu
{
    /** Schermen die in de app zelf zitten: route op de server => scherm in de app. */
    public const SCREENS = [
        'dashboard' => 'vandaag',
        'attention.index' => 'inbox',
        'projects.index' => 'projecten',
        'planning.index' => 'planning',
        'tasks.index' => 'taken',
    ];

    /**
     * @return list<array{label: string, icon: string, route: string, section: string, count?: string}>
     */
    public static function tabs(User $user): array
    {
        return array_values(array_filter([
            self::item('Vandaag', 'sun', 'dashboard', 'vandaag'),
            $user->can('manage-crm')
                ? self::item('Inbox', 'exclamation-triangle', 'attention.index', 'inbox', 'attention')
                : self::item('Chat', 'chat-bubble', 'chat.index', 'teamchat', 'chat'),
            self::item('Projecten', 'folder', 'projects.index', 'projecten'),
            self::item('Planning', 'calendar', 'planning.index', 'uitvoering'),
        ]));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groups(User $user): array
    {
        $crm = $user->can('manage-crm');

        return array_filter([
            'Werkdag' => array_values(array_filter([
                self::item('Vandaag', 'sun', 'dashboard', 'vandaag'),
                $crm ? [...self::item('Inbox', 'exclamation-triangle', 'attention.index', 'inbox', 'attention'), 'accent' => true] : null,
                self::item('Teamchat', 'chat-bubble', 'chat.index', 'teamchat', 'chat'),
                self::item('Meldingen', 'bell', 'notifications.index', 'meldingen', 'notifications'),
                $crm ? ['label' => 'Nova', 'icon' => 'sparkles', 'nova' => true] : null,
            ])),
            'Werk' => array_values(array_filter([
                $crm ? self::item('Verkoop', 'funnel', 'leads.index', 'verkoop') : null,
                $crm ? self::item('Calculatie & offertes', 'document-text', config('renovion.modules.quotes') ? 'quotes.index' : 'calculations.index', 'offertes') : null,
                self::item('Projecten', 'folder', 'projects.index', 'projecten'),
                self::item('Planning & uitvoering', 'calendar', 'planning.index', 'uitvoering'),
                self::item('Gespreksverslagen', 'microphone', 'meetings.index', 'uitvoering'),
            ])),
            'Bedrijf' => $crm ? array_values(array_filter([
                $user->can('manage-team') ? self::item('Mensen & partners', 'users', 'team.index', 'mensen') : null,
                self::item('Organisatie', 'cog', config('renovion.modules.automations') ? 'automations.index' : 'notification-settings.edit', 'organisatie'),
            ])) : [],
        ]);
    }

    /**
     * @return list<array{label: string, icon: string, route: string}>
     */
    public static function plus(User $user): array
    {
        if ($user->cannot('manage-crm')) {
            return [
                ['label' => 'Foto bij project', 'icon' => 'camera', 'route' => 'projects.index'],
                ['label' => 'Taak', 'icon' => 'clipboard-check', 'route' => 'tasks.index'],
                ['label' => 'Gesprek opnemen', 'icon' => 'microphone', 'route' => 'meetings.create'],
                ['label' => 'Bericht aan team', 'icon' => 'chat-bubble', 'route' => 'chat.index'],
            ];
        }

        return array_values(array_filter([
            ['label' => 'Nieuwe aanvraag / lead', 'icon' => 'funnel', 'route' => 'leads.create'],
            ['label' => 'Nieuw project', 'icon' => 'folder', 'route' => 'projects.create'],
            ['label' => 'Taak', 'icon' => 'clipboard-check', 'route' => 'tasks.index'],
            ['label' => 'Werk inplannen', 'icon' => 'calendar', 'route' => 'planning.index'],
            ['label' => 'Gesprek opnemen', 'icon' => 'microphone', 'route' => 'meetings.create'],
            ['label' => 'Calculatie', 'icon' => 'calculator', 'route' => 'calculations.create'],
            config('renovion.modules.quotes') ? ['label' => 'Offerte', 'icon' => 'document-text', 'route' => 'quotes.create'] : null,
        ]));
    }

    /**
     * Adres voor een serverpagina: geopend vanuit de app (session app_bundle) gaan de schermen die in de
     * app zitten terug naar de app, de rest blijft op de server.
     */
    public static function href(string $route, ?string $bundle): string
    {
        return $bundle && isset(self::SCREENS[$route])
            ? $bundle.'/#/'.self::SCREENS[$route]
            : route($route);
    }

    /**
     * Het menu voor de schermen in de app: per item het scherm in de app, of anders het pad op de server.
     *
     * @return array<string, mixed>
     */
    public static function forApp(User $user): array
    {
        $map = fn (array $item) => [
            'label' => $item['label'],
            'icon' => $item['icon'],
            'screen' => isset($item['route']) ? (self::SCREENS[$item['route']] ?? null) : null,
            'path' => isset($item['route']) ? route($item['route'], [], false) : null,
            'count' => $item['count'] ?? null,
            'accent' => $item['accent'] ?? false,
            'nova' => $item['nova'] ?? false,
        ];

        return [
            'tabs' => array_map($map, self::tabs($user)),
            'groups' => array_map(fn (string $name, array $items) => ['name' => $name, 'items' => array_map($map, $items)], array_keys(self::groups($user)), self::groups($user)),
            'plus' => array_map($map, self::plus($user)),
        ];
    }

    /** @return array{label: string, icon: string, route: string, section: string, count?: string} */
    private static function item(string $label, string $icon, string $route, string $section, ?string $count = null): array
    {
        return array_filter(['label' => $label, 'icon' => $icon, 'route' => $route, 'section' => $section, 'count' => $count]);
    }
}
