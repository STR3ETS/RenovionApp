<?php

namespace App\Support\App;

/**
 * Lichte kleuren voor de schermen in de app (mobile/app). De website gebruikt de donkere varianten
 * (AgentRegistry::chipClasses, badgeClasses); de app krijgt hier dezelfde betekenis in de lichte tinten,
 * die de donkere weergave van de app zelf omzet. Let op: de app-build scant alleen app/Enums, dus een nieuwe
 * klasse hier ook in de lijst bovenin mobile/app/screens/agents.js zetten.
 */
class AppColours
{
    /** Icon-chip of pil in de kleur van een agent, categorie of module. */
    public static function chip(?string $colour): string
    {
        return match ($colour) {
            'brand' => 'bg-brand-100 text-brand-700',
            'lead' => 'bg-lead-100 text-lead-700',
            'calc' => 'bg-calc-100 text-calc-700',
            'klant' => 'bg-klant-100 text-klant-700',
            'offerte' => 'bg-offerte-100 text-offerte-700',
            'project' => 'bg-project-100 text-project-700',
            'planning' => 'bg-planning-100 text-planning-700',
            'amber' => 'bg-amber-100 text-amber-800',
            'green' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    /** Agent als klein rondje met de eerste letter. */
    public static function agent(array $agent): array
    {
        return [
            'name' => $agent['name'],
            'initial' => mb_substr($agent['name'], 0, 1),
            'classes' => self::chip($agent['colour']),
        ];
    }

    /** Pad op de server van een absolute url uit route(), zodat de app hem met go() kan openen. */
    public static function path(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }
}
