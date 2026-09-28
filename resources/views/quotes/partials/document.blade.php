{{-- Het offertedocument zoals de klant het ziet (briefing §6). --}}
@php
    $stelposten = $quote->lines->where('is_estimate', true);
    $posten = $quote->lines->where('is_estimate', false);
@endphp

<div class="space-y-8">
    @forelse ($quote->enabledBlocks() as $block)
        @if ($block['key'] === 'investering')
            <section>
                <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $block['title'] }}</h2>
                @if (filled($block['body']))
                    <p class="mb-4 text-sm whitespace-pre-line text-gray-600">{{ $block['body'] }}</p>
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
            </section>
        @elseif ($block['key'] === 'stelposten')
            @continue($stelposten->isEmpty() && blank($block['body']))
            <section>
                <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $block['title'] }}</h2>
                @if (filled($block['body']))
                    <p class="text-sm whitespace-pre-line text-gray-600">{{ $block['body'] }}</p>
                @endif
                @if ($stelposten->isNotEmpty())
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($stelposten as $line)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-600">{{ $line->description }}</span>
                                <span class="font-semibold whitespace-nowrap text-navy-900">€ {{ number_format((float) $line->total, 2, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @else
            @continue(blank($block['body']))
            <section>
                <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $block['title'] }}</h2>
                <p class="text-sm whitespace-pre-line text-gray-600">{{ $block['body'] }}</p>
            </section>
        @endif
    @empty
        <p class="text-sm text-gray-400">Deze offerte heeft nog geen inhoud — open de tab "Teksten" om de blokken te vullen.</p>
    @endforelse
</div>
