<?php

namespace App\Os\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Nova, de assistent in de app. Een vraag gaat met het gesprek naar Claude,
 * die in de echte gegevens kijkt en antwoordt, en voor elke wijziging een
 * voorstel doet. Voorstellen worden op de server bewaard; bevestigen voert
 * precies dat gecontroleerde voorstel uit.
 */
final class Nova
{
    /**
     * @param  list<array{r: string, t: string}>  $history  eerdere berichten (u = gebruiker, n = Nova)
     * @return array{tekst: string, voorstellen: list<array<string, mixed>>}
     */
    public function ask(User $user, string $question, array $history, ?string $projectId, string $mode, string $route): array
    {
        if (! Claude::enabled()) {
            throw new AiUnavailable('Nova heeft nog geen AI-sleutel. Zet ANTHROPIC_API_KEY in .env om Nova te laten werken.');
        }

        $today = now()->toDateString();
        $state = new StateReader($user);
        $messages = [];
        foreach (array_slice($history, -12) as $m) {
            $text = trim((string) ($m['t'] ?? ''));
            if ($text === '') {
                continue;
            }
            $role = ($m['r'] ?? '') === 'u' ? 'user' : 'assistant';
            if (! $messages && $role === 'assistant') {
                continue;
            }
            if ($messages && end($messages)['role'] === $role) {
                $messages[array_key_last($messages)]['content'] .= "\n\n".$text;
            } else {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        if ($messages && end($messages)['role'] === 'user') {
            $messages[array_key_last($messages)]['content'] .= "\n\n".$question;
        } else {
            $messages[] = ['role' => 'user', 'content' => $question];
        }

        $result = (new Claude)->run(
            $this->system($state, $today, $projectId, $mode, $route),
            $messages,
            new ReadTools($state, $today),
            new Actions($state, $today),
        );

        if ($mode === 'advies') {
            // Alleen adviseren: de voorstellen worden een advies in de tekst, er valt niets te bevestigen.
            foreach ($result['voorstellen'] as $p) {
                $result['tekst'] .= "\n\nMijn advies: ".implode('; ', $p['changes']).'.';
            }
            $result['voorstellen'] = [];
        }

        foreach ($result['voorstellen'] as $p) {
            Cache::put('nova-voorstel:'.$p['id'], ['user' => $user->id, 'acties' => $p['acties']], now()->addDay());
        }

        return [
            'tekst' => $result['tekst'] ?: ($result['voorstellen'] ? 'Ik heb een voorstel klaargezet.' : 'Daar heb ik geen antwoord op kunnen vinden.'),
            'voorstellen' => array_map(fn ($p) => array_diff_key($p, ['acties' => 1]), $result['voorstellen']),
        ];
    }

    /** @return array{rev: int, rejected: list<string>, samenvatting: list<string>} */
    public function confirm(User $user, string $proposalId): array
    {
        $stored = Cache::get('nova-voorstel:'.$proposalId);
        if (! $stored || $stored['user'] !== $user->id) {
            throw new ActionException('Dit voorstel is verlopen of hoort niet bij jou. Vraag het Nova opnieuw.');
        }
        Cache::forget('nova-voorstel:'.$proposalId);

        return (new Actions(new StateReader($user), now()->toDateString()))->execute($user, $stored['acties']);
    }

    private function system(StateReader $s, string $today, ?string $projectId, string $mode, string $route): string
    {
        $me = $s->me();
        $project = $projectId ? $s->project($projectId) : null;
        $weekday = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'][(int) date('w', strtotime($today))];
        $modeText = match ($mode) {
            'advies' => 'De werkwijze staat op "Alleen adviseren": je mag een voorstel doen, maar het wordt als advies getoond en niet uitgevoerd.',
            'auto' => 'De werkwijze staat op "Veilige acties direct": interne taken, notities, afspraken en chatberichten voert de app direct uit. Al het andere wacht op bevestiging.',
            default => 'De werkwijze staat op "Stelt voor, jij bevestigt": elke wijziging wacht op bevestiging.',
        };

        $cal = StateReader::calendar($today);

        return <<<TXT
Je bent Nova, de assistent in Renovion OS, het bedrijfssysteem van Renovion Groep (renovatiebedrijf in Arnhem: stucwerk, schilderwerk, tegelwerk, badkamers, keukens, aanbouw en complete renovaties).

Je praat met {$s->myName()} (rol: {$s->user->role->label()}, medewerker-id: {$s->user->person_id}). Vandaag is het {$weekday} {$today}. De gebruiker kijkt nu naar het scherm "{$route}".
{$this->projectLine($project)}

Werkwijze:
- Kijk altijd eerst in de gegevens met zoeken, dossier of overzicht voordat je iets beweert of voorstelt. Verzin geen namen, bedragen, datums of id's. Staat iets er niet in, zeg dat.
- Wil de gebruiker dat er iets verandert of gebeurt (taak, afspraak, notitie, bericht aan klant, mail, inplannen, prijs invullen, nieuwe aanvraag, nieuwe lead), gebruik dan het gereedschap voorstel. Een aanvraag is een concrete klus en krijgt een dossier; iemand die we alleen gesproken hebben en als klant willen binnenhalen is een lead (zonder dossier). Je voert zelf niets uit: de gebruiker bevestigt. {$modeText}
- Beantwoord elke vraag in je bericht, kort en concreet, met de cijfers en namen uit de gegevens, ook als je daarnaast een voorstel doet.
- Kalender: {$cal}. Een dagnaam ("vrijdag") is de eerstvolgende die nog komt; is het vandaag die dag, dan bedoelt de gebruiker volgende week, tenzij er "vandaag" staat. Twijfel je, kies de logische datum en noem hem in je antwoord; vraag niet terug. Geen tijd genoemd voor een afspraak: kies 10:00 en zeg dat erbij.
- Taken zonder genoemde eigenaar zijn voor de gebruiker zelf. Berichten aan klanten schrijf je in nette u-vorm, namens Renovion; interne berichten in je-vorm.
- Bedragen schrijf je als € 1.250,00. Antwoord in het Nederlands, zonder opsommingstekens als middelpunt (·) en zonder lange gedachtestreepjes; gebruik komma's en dubbele punten. Houd antwoorden kort: een paar zinnen of een korte lijst.
TXT;
    }

    private function projectLine(?array $p): string
    {
        return $p
            ? "De gebruiker heeft het dossier {$p['id']} ({$p['naam']}) open. Vragen zonder ander project gaan over dit dossier."
            : 'Er is geen dossier geopend; vragen gaan over de hele organisatie.';
    }
}
