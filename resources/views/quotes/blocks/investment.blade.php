@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
    $posten = $quote->lines->where('is_estimate', false);
    $stelposten = $quote->lines->where('is_estimate', true);
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Investering" class="mb-1 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.intro" @blur="save()" data-ph="Korte inleiding (optioneel)" class="mb-4 text-sm text-gray-500"></div>
    @else
        <h2 class="mb-1 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Investering') !!}</h2>
        @if (filled($d['intro'] ?? ''))
            <div class="mb-4 text-sm text-gray-500">{!! QuoteText::render($d['intro']) !!}</div>
        @endif
    @endif

    @if ($isEdit && $quote->lines->isEmpty())
        <a href="{{ route('quotes.edit', $quote) }}" class="mb-3 flex items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-300 p-4 text-xs font-semibold text-gray-400 transition hover:border-brand-400 hover:text-brand-600">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Nog geen posten - voeg ze toe via "Posten bewerken"
        </a>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200">
        <table class="w-full text-sm">
            <tbody>
                @foreach ($posten as $line)
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium text-navy-900">
                            {{ $line->description }}
                            @if ((float) $line->quantity != 1.0)
                                <span class="text-xs text-gray-400">({{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }} {{ $line->unit }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $line->total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @foreach ($stelposten as $line)
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <td class="px-4 py-3 font-medium text-navy-900">{{ $line->description }} <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">stelpost</span></td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $line->total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Subtotaal excl. btw</td>
                    <td class="px-4 py-2.5 text-right font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $quote->subtotal, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Btw</td>
                    <td class="px-4 py-2.5 text-right font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $quote->vat_amount, 2, ',', '.') }}</td>
                </tr>
                <tr class="border-t-2 border-navy-950/10">
                    <td class="px-4 py-3 text-right text-sm font-bold text-navy-950">Totaal incl. btw</td>
                    <td class="px-4 py-3 text-right text-base font-bold whitespace-nowrap text-brand-600">€ {{ number_format((float) $quote->total, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($isEdit)
        <div x-rich="block.data.note" @blur="save()" data-ph="Notitie onder de tabel (bijv. over stelposten)" class="mt-3 text-xs text-gray-500"></div>
        <p class="mt-2 text-[11px] text-gray-400">De posten en bedragen bewerk je via <a href="{{ route('quotes.edit', $quote) }}" class="font-semibold text-brand-600 hover:text-brand-500">Posten bewerken</a>.</p>
    @elseif ($stelposten->isNotEmpty() && filled($d['note'] ?? ''))
        <div class="mt-3 text-xs text-gray-500">{!! QuoteText::render($d['note']) !!}</div>
    @endif
</section>
