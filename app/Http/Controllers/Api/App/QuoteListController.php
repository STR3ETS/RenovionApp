<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Offertes in de app (quotes/index): statusfilter en per 25, zoals de website. Elke offerte opent het
 * offertescherm van de app (#/offerte/{id}, Api\App\QuoteController).
 */
class QuoteListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $status = $request->filled('status') ? QuoteStatus::from($request->query('status')) : null;

        $quotes = Quote::with(['customer', 'lead'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25, page: $request->integer('pagina', 1));

        return response()->json([
            'total' => $quotes->total(),
            'status' => $status?->value,
            'statuses' => array_map(fn (QuoteStatus $option) => ['value' => $option->value, 'label' => $option->label()], QuoteStatus::cases()),
            'page' => $quotes->currentPage(),
            'more' => $quotes->hasMorePages(),
            'quotes' => $quotes->getCollection()->map(function (Quote $quote) {
                $daysOpen = $quote->daysOpen();

                return [
                    'id' => $quote->id,
                    'customer' => $quote->customer->name,
                    'title' => $quote->lead?->service,
                    'sub' => $quote->number.', € '.number_format((float) $quote->total, 2, ',', '.')
                        .($daysOpen !== null ? ", {$daysOpen} dagen open" : '')
                        .($quote->viewed_count > 0 ? ", {$quote->viewed_count}× bekeken" : ''),
                    'signal' => match (true) {
                        $daysOpen !== null && $daysOpen >= 5 => ['color' => 'red', 'text' => "Al {$daysOpen} dagen geen reactie"],
                        $daysOpen !== null && $daysOpen >= 3 => ['color' => 'amber', 'text' => 'Opvolgen'],
                        default => null,
                    },
                    'status' => ['value' => $quote->status->value, 'label' => $quote->status->label(), 'classes' => $quote->status->appBadgeClasses()],
                    'path' => route('quotes.show', $quote, false),
                ];
            }),
            'links' => [
                'create' => route('quotes.create', [], false),
                'dashboard' => route('quote-dashboard', [], false),
            ],
        ]);
    }
}
