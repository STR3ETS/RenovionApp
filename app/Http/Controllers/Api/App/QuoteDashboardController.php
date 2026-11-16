<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\QuoteDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Het offertedashboard in de app (quotes/dashboard): dezelfde cijfers uit QuoteDashboard, met periode en verkoper.
 */
class QuoteDashboardController extends Controller
{
    private const STATES = [
        'open' => ['Open', 'bg-lead-100 text-lead-700'],
        'getekend' => ['Getekend', 'bg-green-100 text-green-800'],
        'afgewezen' => ['Afgewezen', 'bg-red-100 text-red-800'],
        'verlopen' => ['Verlopen', 'bg-gray-200 text-gray-600'],
    ];

    public function __invoke(Request $request, QuoteDashboard $dashboard): JsonResponse
    {
        $period = $request->string('periode')->toString();
        $period = array_key_exists($period, QuoteDashboard::PERIODS) ? $period : 'jaar';
        $d = $dashboard->build($period, $request->integer('verkoper') ?: null);

        $eur = fn (float $value) => '€ '.number_format($value, 0, ',', '.');
        $pct = fn (?float $value) => $value === null ? 'n.v.t.' : rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',').'%';
        $days = fn (?float $value) => $value === null ? null : rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',').' dagen';

        return response()->json([
            'period' => $d['period'],
            'seller' => $d['seller'],
            'periods' => collect(QuoteDashboard::PERIODS)->map(fn (string $label, string $key) => ['value' => (string) $key, 'label' => $label])->values(),
            'sellers' => User::query()
                ->whereIn('role', [UserRole::Sales->value, UserRole::Directie->value, UserRole::Admin->value])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]),
            'kpis' => [
                ['label' => 'Gewogen open', 'value' => $eur($d['weighted']), 'sub' => 'kans maal waarde over '.$d['open']->count().' open offertes', 'dark' => true],
                ['label' => 'Verstuurd', 'value' => (string) $d['count'], 'sub' => $eur($d['value'])],
                ['label' => 'Getekend', 'value' => (string) $d['won']->count(), 'sub' => $eur($d['won_value'])],
                ['label' => 'Hitrate op aantal', 'value' => $pct($d['hit_rate']), 'sub' => 'op geld '.$pct($d['hit_rate_value'])],
                ['label' => 'Wordt geopend', 'value' => $pct($d['open_rate']), 'sub' => $d['days_to_open'] === null ? 'nog geen meting' : 'gemiddeld na '.$days($d['days_to_open'])],
            ],
            'rows' => $d['rows']->map(function (array $row) use ($eur) {
                $quote = $row['quote'];
                [$label, $classes] = self::STATES[$row['state']];

                return [
                    'id' => $quote->id,
                    'customer' => $quote->customer->name,
                    'state' => ['value' => $row['state'], 'label' => $label, 'classes' => $classes],
                    'questions' => $quote->questions_count > 0
                        ? $quote->questions_count.' '.($quote->questions_count === 1 ? 'vraag' : 'vragen')
                        : null,
                    'sub' => $quote->number.', verstuurd '.$quote->sent_at->translatedFormat('j M').', '
                        .($quote->views_count > 0
                            ? $quote->views_count.' keer geopend, laatst '.$quote->views->first()->viewed_at->diffForHumans()
                            : 'nog niet geopend'),
                    'total' => $eur((float) $quote->total),
                    'note' => match (true) {
                        $row['state'] === 'open' => 'kans '.round($row['chance'] * 100).'%, gewogen '.$eur((float) $quote->total * $row['chance']),
                        $row['days_to_sign'] !== null => 'getekend na '.$row['days_to_sign'].' dagen',
                        default => null,
                    },
                    'signed' => $row['state'] !== 'open' && $row['days_to_sign'] !== null,
                    'path' => route('quotes.show', $quote, false),
                ];
            }),
            'lead_times' => [
                ['label' => 'Tot eerste opening', 'value' => $days($d['days_to_open']) ?? 'geen meting'],
                ['label' => 'Tot ondertekening', 'value' => $days($d['days_to_sign']) ?? 'geen meting'],
                ['label' => 'Gemiddelde offerte', 'value' => $eur($d['average_value'])],
            ],
            'lost' => $d['lost_reasons']->isEmpty() ? null : [
                'sub' => $d['lost']->count().' afgewezen, '.$eur($d['lost_value']).' aan waarde.',
                'reasons' => $d['lost_reasons']->map(fn (int $count, string $reason) => [
                    'reason' => $reason,
                    'count' => $count,
                    'width' => (int) round($count / max(1, $d['lost_reasons']->max()) * 100),
                ])->values(),
            ],
        ]);
    }
}
