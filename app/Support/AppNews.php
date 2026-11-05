<?php

namespace App\Support;

/**
 * "Wat is nieuw" in het hulpvenster van de iOS/Android-app (serverschil en, via api/app/ik, de schermen in de
 * app zelf). Nieuwste bovenaan, kort en in gewone taal; houd het bij een stuk of vijf.
 */
class AppNews
{
    /** @var list<string> */
    public const ITEMS = [
        'Taken en het projectdossier staan nu in de app zelf en openen meteen.',
        'Een tik op een pushmelding opent direct het juiste scherm.',
        'Geen verbinding? Je ziet de laatste gegevens, met de tijd erbij.',
        'Tik op een kaart om hem open of dicht te klappen.',
        'Alle schermen laden bij het opstarten alvast op de achtergrond.',
    ];
}
