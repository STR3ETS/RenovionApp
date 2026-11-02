@php
    $quote = $project->quote;
    $eur = fn (float $v) => 'EUR '.number_format($v, 2, ',', '.');
@endphp

@if ($quote === null)
    <x-empty-state icon="document-text" category="offerte" title="Geen offerte aan dit project gekoppeld"
                   subtitle="Een project ontstaat normaal uit een getekende offerte. Is dit project handmatig aangemaakt, dan kan die koppeling ontbreken." />
@else
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-white">{{ $quote->number }}</h2>
                        <p class="text-xs text-slate-400">Versie {{ $quote->version }}, {{ $quote->created_at->translatedFormat('j M Y') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $quote->status->badgeClasses() }}">{{ $quote->status->label() }}</span>
                        <a href="{{ route('quotes.show', $quote) }}"
                           class="rounded-full border border-surface-600 px-3.5 py-2 text-xs font-semibold text-slate-300 transition hover:text-white">
                            Offerte openen
                        </a>
                    </div>
                </div>

                <dl class="grid gap-4 border-t border-surface-700 pt-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Subtotaal</dt>
                        <dd class="mt-1 text-base font-bold text-white">{{ $eur((float) $quote->subtotal) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Btw</dt>
                        <dd class="mt-1 text-base font-bold text-slate-300">{{ $eur((float) $quote->vat_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Totaal</dt>
                        <dd class="mt-1 text-base font-bold text-white">{{ $eur((float) $quote->total) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Door de klant</dt>
                        <dd class="mt-1 text-sm text-slate-300">
                            @if ($quote->accepted_at)
                                Getekend {{ $quote->accepted_at->translatedFormat('j M Y') }}
                            @elseif ($quote->viewed_count > 0)
                                {{ $quote->viewed_count }} keer geopend
                            @else
                                Nog niet geopend
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            @if ($quote->versions->isNotEmpty())
                <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-3 text-sm font-bold text-white">Versie<span class="text-brand-500">geschiedenis</span></h2>
                    <div class="space-y-2">
                        @foreach ($quote->versions as $version)
                            <div class="border-b border-surface-700 pb-2 last:border-b-0 last:pb-0">
                                <p class="text-sm font-semibold text-white">Versie {{ $version->version }}</p>
                                <p class="text-xs text-slate-400">{{ $version->change_log ?: 'geen wijzigingslog' }}</p>
                                <p class="text-xs text-slate-500">{{ $version->created_at->translatedFormat('j M Y H:i') }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Klant<span class="text-brand-500">link</span></h2>
                <p class="mb-3 text-xs text-slate-400">De link waarop de klant de offerte leest en digitaal tekent.</p>
                <a href="{{ route('quotes.public', $quote->public_token) }}" target="_blank" rel="noopener"
                   class="block w-full rounded-xl border border-surface-600 py-2.5 text-center text-sm font-semibold text-slate-200 transition hover:text-white">
                    Bekijken zoals de klant
                </a>
            </section>

            @if ($quote->views->isNotEmpty())
                <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-3 text-sm font-bold text-white">Geopend</h2>
                    <div class="space-y-1.5">
                        @foreach ($quote->views->take(8) as $view)
                            <p class="text-sm text-slate-300">{{ $view->viewed_at->translatedFormat('j M Y H:i') }}</p>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endif
