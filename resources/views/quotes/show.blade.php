<x-layouts.app :title="'Offerte '.$quote->number">

    <x-page-header :title="$quote->number" :subtitle="$quote->customer->name.' · aangemaakt '.$quote->created_at->translatedFormat('j M Y')">
        <x-status-badge :status="$quote->status" class="text-sm" />
        @if ($quote->status === \App\Enums\QuoteStatus::Concept)
            <a href="{{ route('quotes.edit', $quote) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Bewerken</a>
        @endif
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Signalering --}}
            @if ($quote->daysOpen() !== null && $quote->daysOpen() >= 3)
                <div class="rounded-xl border p-4 {{ $quote->daysOpen() >= 5 ? 'border-red-300 bg-red-50' : 'border-amber-300 bg-amber-50' }}">
                    <p class="flex items-center gap-2 text-sm font-semibold {{ $quote->daysOpen() >= 5 ? 'text-red-800' : 'text-amber-800' }}">
                        <x-signal-dot :color="$quote->daysOpen() >= 5 ? 'red' : 'amber'" />
                        Offerte staat {{ $quote->daysOpen() }} dagen open
                        @if ($quote->viewed_count > 0) · {{ $quote->viewed_count }}× bekeken @endif
                        — vandaag opvolgen.
                    </p>
                    @if ($quote->customer->phone)
                        <a href="tel:{{ $quote->customer->phone }}" class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500"><x-icon name="phone" /> {{ $quote->customer->name }} bellen</a>
                    @endif
                </div>
            @endif

            {{-- Regels --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-navy-900">Werkzaamheden</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                                <th class="pb-2 font-semibold">Werkzaamheid</th>
                                <th class="pb-2 text-right font-semibold">Hoeveelheid</th>
                                <th class="pb-2 text-right font-semibold">Prijs p/e</th>
                                <th class="pb-2 text-right font-semibold">BTW</th>
                                <th class="pb-2 text-right font-semibold">Totaal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quote->lines as $line)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2.5 font-medium text-navy-900">{{ $line->description }}</td>
                                    <td class="py-2.5 text-right text-gray-600">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }} {{ $line->unit }}</td>
                                    <td class="py-2.5 text-right text-gray-600">€ {{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                                    <td class="py-2.5 text-right text-gray-600">{{ rtrim(rtrim(number_format((float) $line->vat_rate, 1, ',', '.'), '0'), ',') }}%</td>
                                    <td class="py-2.5 text-right font-semibold text-navy-900">€ {{ number_format((float) $line->total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <dl class="mt-4 ml-auto w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotaal</dt><dd class="font-semibold">€ {{ number_format((float) $quote->subtotal, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">BTW</dt><dd class="font-semibold">€ {{ number_format((float) $quote->vat_amount, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 text-base"><dt class="font-bold text-navy-900">Totaal</dt><dd class="font-bold text-navy-900">€ {{ number_format((float) $quote->total, 2, ',', '.') }}</dd></div>
                </dl>
            </section>

            @if ($quote->notes)
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-2 text-sm font-bold text-navy-900">Opmerkingen (intern)</h2>
                    <p class="text-sm whitespace-pre-line text-gray-600">{{ $quote->notes }}</p>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Statusacties --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Acties</h2>

                @if ($quote->status->isOpen())
                    <div class="space-y-2">
                        @if ($quote->status === \App\Enums\QuoteStatus::Concept)
                            <form method="POST" action="{{ route('quotes.status', $quote) }}">
                                @csrf @method('PATCH')
                                <button type="submit" name="status" value="verstuurd" class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-navy-900 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800"><x-icon name="paper-airplane" /> Markeren als verstuurd</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('quotes.status', $quote) }}">
                                @csrf @method('PATCH')
                                <button type="submit" name="status" value="bekeken" class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-300 bg-white py-2.5 text-sm font-semibold text-navy-900 transition hover:border-brand-400"><x-icon name="eye" /> Klant heeft bekeken</button>
                            </form>
                            <form method="POST" action="{{ route('quotes.status', $quote) }}">
                                @csrf @method('PATCH')
                                <button type="submit" name="status" value="opvolgen" class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-300 bg-white py-2.5 text-sm font-semibold text-navy-900 transition hover:border-brand-400"><x-icon name="bell" /> Markeren als opvolgen</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('quotes.status', $quote) }}"
                              onsubmit="return confirm('Offerte akkoord? Er wordt automatisch een project aangemaakt.');">
                            @csrf @method('PATCH')
                            <button type="submit" name="status" value="akkoord" class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-green-600 py-2.5 text-sm font-semibold text-white transition hover:bg-green-500"><x-icon name="check" /> Akkoord — maak project</button>
                        </form>
                        <form method="POST" action="{{ route('quotes.status', $quote) }}"
                              onsubmit="return confirm('Offerte markeren als afgewezen?');">
                            @csrf @method('PATCH')
                            <button type="submit" name="status" value="afgewezen" class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-red-300 bg-white py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"><x-icon name="x-mark" /> Afgewezen</button>
                        </form>
                    </div>
                @elseif ($quote->project)
                    <a href="{{ route('projects.show', $quote->project) }}" class="block rounded-xl bg-navy-900 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-navy-800">→ Naar project</a>
                @else
                    <p class="text-sm text-gray-400">Deze offerte is afgehandeld.</p>
                @endif
            </section>

            {{-- Verloop --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Verloop</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Aangemaakt</dt><dd class="font-medium">{{ $quote->created_at->translatedFormat('j M Y H:i') }}</dd></div>
                    <div><dt class="text-gray-500">Verstuurd</dt><dd class="font-medium">{{ $quote->sent_at?->translatedFormat('j M Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Laatst bekeken</dt><dd class="font-medium">{{ $quote->viewed_at?->translatedFormat('j M Y H:i') ?? '—' }} @if ($quote->viewed_count > 0) ({{ $quote->viewed_count }}×) @endif</dd></div>
                    <div><dt class="text-gray-500">Geldig tot</dt><dd class="font-medium">{{ $quote->valid_until?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                    @if ($quote->accepted_at)
                        <div><dt class="text-gray-500">Akkoord op</dt><dd class="font-medium text-green-700">{{ $quote->accepted_at->translatedFormat('j M Y H:i') }}</dd></div>
                    @endif
                    @if ($quote->rejected_at)
                        <div><dt class="text-gray-500">Afgewezen op</dt><dd class="font-medium text-red-700">{{ $quote->rejected_at->translatedFormat('j M Y H:i') }}</dd></div>
                    @endif
                </dl>
            </section>

            {{-- Klant --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Klant</h2>
                <p class="text-sm font-semibold text-navy-900">{{ $quote->customer->name }}</p>
                <p class="text-sm text-gray-500">{{ $quote->customer->city ?? '—' }}</p>
                <a href="{{ route('customers.show', $quote->customer) }}" class="mt-2 inline-block text-xs font-semibold text-steel-600 hover:text-steel-500">Klantdossier →</a>
            </section>

            @if ($quote->status === \App\Enums\QuoteStatus::Concept)
                <form method="POST" action="{{ route('quotes.destroy', $quote) }}" onsubmit="return confirm('Conceptofferte verwijderen?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full rounded-xl border border-red-200 py-2.5 text-sm font-semibold text-red-500 transition hover:bg-red-50">Concept verwijderen</button>
                </form>
            @endif
        </div>
    </div>

</x-layouts.app>
