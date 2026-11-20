<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Customer;
use App\Models\InboundMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Leest uit een binnengekomen bericht wat er gevraagd wordt: het werk, het
 * adres, de deadline en bij welke klant het hoort. Dat is altijd een voorstel:
 * een mens bevestigt het voordat er iets mee gebeurt.
 *
 * Zonder AI-sleutel, of als het model niet antwoordt, valt hij terug op vaste
 * patronen. Die halen minder uit vrije tekst, maar het scherm werkt dan nog.
 */
class InboundMailReader
{
    /**
     * @return array{werk: ?string, adres: ?string, deadline: ?string, klant_id: ?int, klant_reden: ?string, bron: string}
     */
    public function read(InboundMail $mail): array
    {
        $text = trim(($mail->subject ? $mail->subject."\n" : '').$mail->body);
        $result = $this->byRules($mail, $text);

        if (filled(config('renovion.ai.api_key'))) {
            try {
                $fromModel = array_filter($this->byModel($text), fn ($value) => filled($value));
                $result = [...$result, ...$fromModel, 'bron' => 'nova'];
            } catch (Throwable $e) {
                // Een storing bij het model mag de aanvraagmail niet stilleggen.
                Log::warning('Aanvraagmail: het model gaf geen antwoord, vaste patronen gebruikt.', ['fout' => $e->getMessage()]);
            }
        }

        return [...$result, ...$this->matchCustomer($mail, $text)];
    }

    /**
     * Bij welke klant hoort dit? Eerst een klantnummer in de tekst, dan het
     * mailadres, dan het domein van een zakelijke klant.
     *
     * @return array{klant_id: ?int, klant_reden: ?string}
     */
    public function matchCustomer(InboundMail $mail, string $text): array
    {
        if ($mail->customer_id !== null) {
            return ['klant_id' => $mail->customer_id, 'klant_reden' => 'via de persoonlijke opdrachtlink'];
        }

        if (preg_match('/\bK-\d{4,}\b/i', $text, $match) === 1) {
            $customer = Customer::firstWhere('number', strtoupper($match[0]));

            if ($customer !== null) {
                return ['klant_id' => $customer->id, 'klant_reden' => 'klantnummer '.$customer->number.' staat in het bericht'];
            }
        }

        $email = strtolower((string) $mail->from_email);

        if ($email !== '') {
            $customer = Customer::whereRaw('lower(email) = ?', [$email])->first();

            if ($customer !== null) {
                return ['klant_id' => $customer->id, 'klant_reden' => 'het mailadres is van deze klant'];
            }

            // Een collega van een zakelijke klant mailt vanaf hetzelfde domein.
            $domain = str($email)->after('@')->value();

            if ($domain !== '' && ! in_array($domain, self::PUBLIC_DOMAINS, true)) {
                $customer = Customer::where('type', 'zakelijk')
                    ->whereRaw('lower(email) like ?', ['%@'.$domain])
                    ->first();

                if ($customer !== null) {
                    return ['klant_id' => $customer->id, 'klant_reden' => 'het mailadres is van '.$domain];
                }
            }
        }

        return ['klant_id' => null, 'klant_reden' => null];
    }

    /** Adressen waar iedereen een account kan hebben: het domein zegt daar niets. */
    private const PUBLIC_DOMAINS = [
        'gmail.com', 'hotmail.com', 'outlook.com', 'live.nl', 'live.com', 'icloud.com', 'me.com',
        'yahoo.com', 'ziggo.nl', 'kpnmail.nl', 'planet.nl', 'xs4all.nl', 'hetnet.nl', 'home.nl', 'upcmail.nl',
    ];

    /**
     * @return array{werk: ?string, adres: ?string, deadline: ?string, bron: string}
     */
    private function byRules(InboundMail $mail, string $text): array
    {
        return [
            'werk' => $this->work($mail),
            'adres' => $this->address($text),
            'deadline' => $this->deadline($text),
            'bron' => 'patronen',
        ];
    }

    /** Het onderwerp, en anders de eerste zin die geen aanhef is. */
    private function work(InboundMail $mail): ?string
    {
        $subject = trim((string) preg_replace('/^((re|fw|fwd|aw)\s*:\s*)+/i', '', (string) $mail->subject));

        if ($subject !== '') {
            return str($subject)->limit(160)->value();
        }

        foreach (preg_split('/\R+/', $mail->body) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && preg_match('/^(beste|geachte|hallo|hoi|goede(morgen|middag|avond)|dag)\b/i', $line) !== 1) {
                return str($line)->limit(160)->value();
            }
        }

        return null;
    }

    /** Een straat met huisnummer, liefst met postcode en plaats erachter. */
    private function address(string $text): ?string
    {
        $street = '[A-Z][\p{L}\'. -]{2,40}?(?:straat|laan|weg|plein|singel|kade|dijk|hof|steeg|pad|gracht|dreef|baan|markt|park|erf|wal|burg)\s+\d{1,4}\s?[a-zA-Z]?';
        $postal = '\d{4}\s?[A-Z]{2}';

        if (preg_match('/('.$street.')(?:,?\s+('.$postal.')(?:\s+([A-Z][\p{L}\'-]+(?:\s[A-Z][\p{L}\'-]+)?))?)?/u', $text, $match) === 1) {
            return trim(collect([$match[1], trim(($match[2] ?? '').' '.($match[3] ?? ''))])->filter()->join(', '));
        }

        if (preg_match('/'.$postal.'\s+[A-Z][\p{L}\'-]+/u', $text, $match) === 1) {
            return trim($match[0]);
        }

        return null;
    }

    /** Een datum achter woorden als "voor", "uiterlijk" of "deadline". */
    private function deadline(string $text): ?string
    {
        $months = 'januari|februari|maart|april|mei|juni|juli|augustus|september|oktober|november|december';
        $lead = '(?:deadline|uiterlijk|v[oó]{2}r|klaar\s+(?:op|voor|v[oó]{2}r)|opleveren\s+(?:op|voor|v[oó]{2}r)|gereed\s+(?:op|voor|v[oó]{2}r))';

        if (preg_match('/'.$lead.'[:\s]+(\d{1,2})[-\/.](\d{1,2})(?:[-\/.](\d{2,4}))?/iu', $text, $match) === 1) {
            return $this->date((int) $match[1], (int) $match[2], isset($match[3]) ? (int) $match[3] : null);
        }

        if (preg_match('/'.$lead.'[:\s]+(\d{1,2})\s+('.$months.')(?:\s+(\d{4}))?/iu', $text, $match) === 1) {
            $month = array_search(mb_strtolower($match[2]), explode('|', $months), true) + 1;

            return $this->date((int) $match[1], $month, isset($match[3]) ? (int) $match[3] : null);
        }

        return null;
    }

    /** Zonder jaar is het de eerstvolgende keer dat die datum voorkomt. */
    private function date(int $day, int $month, ?int $year): ?string
    {
        if ($year !== null && $year < 100) {
            $year += 2000;
        }

        if (! checkdate($month, $day, $year ?? today()->year)) {
            return null;
        }

        $date = Carbon::create($year ?? today()->year, $month, $day);

        if ($year === null && $date->lessThan(today())) {
            $date->addYear();
        }

        return $date->toDateString();
    }

    /**
     * @return array{werk?: ?string, adres?: ?string, deadline?: ?string}
     */
    private function byModel(string $text): array
    {
        $client = new Client(apiKey: (string) config('renovion.ai.api_key'));

        $response = $client->messages->create(
            model: (string) config('renovion.ai.model'),
            maxTokens: 600,
            outputConfig: ['effort' => 'low'],
            system: [[
                'type' => 'text',
                'text' => 'Je leest een bericht dat binnenkwam bij een Nederlands renovatiebedrijf. Haal eruit welk werk er gevraagd wordt, '
                    .'op welk adres en voor welke datum het klaar moet zijn. Verzin niets: wat er niet staat, laat je leeg. '
                    .'Schrijf het werk in een korte zin, zonder aanhef. Gebruik geen middots en geen gedachtestreepjes. Vandaag is '.today()->toDateString().'.',
            ]],
            tools: [[
                'name' => 'aanvraag_vastleggen',
                'description' => 'Leg vast wat er in het bericht gevraagd wordt.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'werk' => ['type' => 'string', 'description' => 'Het gevraagde werk in een korte zin'],
                        'adres' => ['type' => 'string', 'description' => 'Straat, huisnummer, postcode en plaats van het werk'],
                        'deadline' => ['type' => 'string', 'description' => 'Wanneer het klaar moet zijn, als JJJJ-MM-DD'],
                    ],
                ],
            ]],
            toolChoice: ['type' => 'tool', 'name' => 'aanvraag_vastleggen'],
            messages: [['role' => 'user', 'content' => mb_substr($text, 0, 6000)]],
        );

        foreach ($response->content as $block) {
            if ($block->type === 'tool_use') {
                /** @var array<string, mixed> $input */
                $input = json_decode(json_encode($block->input), true);

                $deadline = is_string($input['deadline'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['deadline']) === 1
                    ? $input['deadline']
                    : null;

                return [
                    'werk' => is_string($input['werk'] ?? null) ? str($input['werk'])->limit(160)->value() : null,
                    'adres' => is_string($input['adres'] ?? null) ? str($input['adres'])->limit(200)->value() : null,
                    'deadline' => $deadline,
                ];
            }
        }

        return [];
    }
}
