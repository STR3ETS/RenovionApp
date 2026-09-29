{{-- Het offertedocument (briefing §6): rendert de builder-blokken zoals de klant ze ziet. --}}
@php
    $documentBlocks = \App\Support\QuoteBlockRegistry::ensureTyped($quote->blocks, $quote);
    $stelposten = $quote->lines->where('is_estimate', true);
    $posten = $quote->lines->where('is_estimate', false);
@endphp

<div class="space-y-8">
    @foreach ($documentBlocks as $block)
        @php $d = $block['data'] ?? []; @endphp

        @switch($block['type'])
            @case('hero')
                <header class="overflow-hidden rounded-2xl bg-navy-950 p-8 text-white lg:p-10">
                    <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="mb-6 h-9 w-auto">
                    <p class="text-sm text-navy-300">{{ $d['title'] ?? 'Offerte' }} {{ $quote->number }} · versie v{{ $quote->version }}</p>
                    <h1 class="mt-1 text-2xl font-bold lg:text-3xl">{{ $d['subtitle'] ?? 'Renovatiewerkzaamheden' }}</h1>
                    <p class="mt-1 text-navy-100">{{ $quote->customer->name }}</p>
                    @if ($quote->valid_until)
                        <p class="mt-4 inline-block rounded-full bg-navy-900 px-3 py-1 text-xs font-semibold text-navy-200">Geldig tot {{ $quote->valid_until->translatedFormat('j F Y') }}</p>
                    @endif
                </header>
                @break

            @case('text')
                @if (filled($d['body'] ?? ''))
                    <section>
                        <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $d['title'] ?? '' }}</h2>
                        <p class="text-sm whitespace-pre-line text-gray-600">{{ $d['body'] }}</p>
                    </section>
                @endif
                @break

            @case('list')
                <section>
                    <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $d['title'] ?? '' }}</h2>
                    @if (filled($d['intro'] ?? ''))
                        <p class="mb-3 text-sm whitespace-pre-line text-gray-600">{{ $d['intro'] }}</p>
                    @endif
                    <ul class="space-y-1.5">
                        @foreach (($d['items'] ?? []) as $item)
                            @continue(blank($item))
                            <li class="flex items-start gap-2 text-sm text-gray-600">
                                <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                                <span class="whitespace-pre-line">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
                @break

            @case('phases')
                <section>
                    <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $d['title'] ?? 'Fasering' }}</h2>
                    @if (filled($d['intro'] ?? ''))
                        <p class="mb-4 text-sm whitespace-pre-line text-gray-600">{{ $d['intro'] }}</p>
                    @endif
                    <ol class="space-y-3">
                        @foreach (($d['items'] ?? []) as $fase)
                            <li class="flex gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-white">{{ $loop->iteration }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-navy-900">{{ $fase['title'] ?? '' }}</span>
                                    <span class="block text-sm whitespace-pre-line text-gray-600">{{ $fase['body'] ?? '' }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </section>
                @break

            @case('investment')
                <section>
                    <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $d['title'] ?? 'Investering' }}</h2>
                    @if (filled($d['intro'] ?? ''))
                        <p class="mb-4 text-sm whitespace-pre-line text-gray-600">{{ $d['intro'] }}</p>
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
                    @if ($stelposten->isNotEmpty() && filled($d['note'] ?? ''))
                        <p class="mt-3 text-xs text-gray-500">{{ $d['note'] }}</p>
                    @endif
                </section>
                @break

            @case('faq')
                <section>
                    <h2 class="mb-3 text-lg font-bold text-navy-950">{{ $d['title'] ?? 'Veelgestelde vragen' }}</h2>
                    <div class="space-y-3">
                        @foreach (($d['items'] ?? []) as $item)
                            @continue(blank($item['q'] ?? ''))
                            <div>
                                <p class="text-sm font-bold text-navy-900">{{ $item['q'] }}</p>
                                <p class="text-sm whitespace-pre-line text-gray-600">{{ $item['a'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
                @break

            @case('image')
                @if (filled($d['url'] ?? ''))
                    <figure>
                        <img src="{{ $d['url'] }}" alt="{{ $d['caption'] ?? 'Foto' }}" loading="lazy" class="w-full rounded-2xl object-cover">
                        @if (filled($d['caption'] ?? ''))
                            <figcaption class="mt-2 text-center text-xs text-gray-400">{{ $d['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endif
                @break

            @case('divider')
                <hr class="border-gray-200">
                @break
        @endswitch
    @endforeach
</div>
