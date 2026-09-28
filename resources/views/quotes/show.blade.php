<x-layouts.app :title="'Offerte '.$quote->number">

    <div x-data="{ tab: 'overzicht' }">

        <x-page-header :title="$quote->number.' · v'.$quote->version" :subtitle="$quote->customer->name.' · aangemaakt '.$quote->created_at->translatedFormat('j M Y')">
            <x-status-badge :status="$quote->status" class="text-sm" />
            @if ($quote->status === \App\Enums\QuoteStatus::Concept)
                <a href="{{ route('quotes.edit', $quote) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Posten bewerken</a>
            @endif
        </x-page-header>

        {{-- Tabs (mockup §18) --}}
        <div class="mb-5 flex gap-1 rounded-xl border border-gray-200 bg-white p-1 text-sm font-semibold">
            <button type="button" @click="tab = 'overzicht'" :class="tab === 'overzicht' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="flex-1 rounded-lg px-4 py-2 transition">Overzicht</button>
            <button type="button" @click="tab = 'teksten'" :class="tab === 'teksten' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="flex-1 rounded-lg px-4 py-2 transition">Teksten</button>
            <button type="button" @click="tab = 'preview'" :class="tab === 'preview' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="flex-1 rounded-lg px-4 py-2 transition">Preview (klant)</button>
        </div>

        {{-- ===== Tab: Overzicht ===== --}}
        <div x-show="tab === 'overzicht'">
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">

                    @if ($quote->change_request)
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
                            <p class="flex items-center gap-2 text-sm font-bold text-amber-800"><x-signal-dot color="amber" /> De klant vraagt een aanpassing:</p>
                            <p class="mt-1 text-sm whitespace-pre-line text-amber-800">{{ $quote->change_request }}</p>
                        </div>
                    @endif

                    @if ($quote->daysOpen() !== null && $quote->daysOpen() >= 3)
                        <div class="rounded-xl border p-4 {{ $quote->daysOpen() >= 5 ? 'border-red-300 bg-red-50' : 'border-amber-300 bg-amber-50' }}">
                            <p class="flex items-center gap-2 text-sm font-semibold {{ $quote->daysOpen() >= 5 ? 'text-red-800' : 'text-amber-800' }}">
                                <x-signal-dot :color="$quote->daysOpen() >= 5 ? 'red' : 'amber'" />
                                Offerte staat {{ $quote->daysOpen() }} dagen open
                                @if ($quote->viewed_count > 0) · {{ $quote->viewed_count }}× bekeken @endif
                                — vandaag opvolgen.
                            </p>
                            @if ($quote->customer->phone)
                                <a href="tel:{{ $quote->customer->phone }}" class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"><x-icon name="phone" /> {{ $quote->customer->name }} bellen</a>
                            @endif
                        </div>
                    @endif

                    {{-- Klantlink --}}
                    @if ($quote->status !== \App\Enums\QuoteStatus::Concept)
                        <section class="rounded-2xl border border-gray-200 bg-white p-5" x-data="{ copied: false }">
                            <h2 class="mb-2 text-sm font-bold text-navy-900">Klantlink</h2>
                            <p class="mb-3 text-xs text-gray-400">Via deze privélink bekijkt en ondertekent de klant de offerte. Elke keer dat de klant kijkt, zie je dat hier terug.</p>
                            <div class="flex gap-2">
                                <input type="text" readonly value="{{ $quote->publicUrl() }}" class="flex-1 rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-600">
                                <button type="button"
                                        @click="navigator.clipboard.writeText(@js($quote->publicUrl())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                        class="rounded-xl bg-navy-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-900">
                                    <span x-show="!copied">Kopiëren</span>
                                    <span x-show="copied" x-cloak>Gekopieerd</span>
                                </button>
                                @if ($quote->customer->email)
                                    <a href="mailto:{{ $quote->customer->email }}?subject={{ rawurlencode('Offerte '.$quote->number.' — Renovion') }}&body={{ rawurlencode("Beste {$quote->customer->name},\n\nHierbij ontvangt u onze offerte. U kunt hem bekijken en digitaal ondertekenen via:\n{$quote->publicUrl()}\n\nMet vriendelijke groet,\nRenovion") }}"
                                       class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Mailen</a>
                                @endif
                            </div>
                        </section>
                    @endif

                    {{-- Posten --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="mb-4 text-sm font-bold text-navy-900">Investering</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <tbody>
                                    @foreach ($quote->lines as $line)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2.5 font-medium text-navy-900">
                                                {{ $line->description }}
                                                @if ($line->is_estimate)
                                                    <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">stelpost</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-right text-gray-600">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }} {{ $line->unit }}</td>
                                            <td class="py-2.5 text-right font-semibold text-navy-900">€ {{ number_format((float) $line->total, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <dl class="mt-4 ml-auto w-full max-w-xs space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Subtotaal</dt><dd class="font-semibold">€ {{ number_format((float) $quote->subtotal, 2, ',', '.') }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Btw</dt><dd class="font-semibold">€ {{ number_format((float) $quote->vat_amount, 2, ',', '.') }}</dd></div>
                            <div class="flex justify-between border-t border-gray-200 pt-1 text-base"><dt class="font-bold text-navy-900">Totaal</dt><dd class="font-bold text-navy-900">€ {{ number_format((float) $quote->total, 2, ',', '.') }}</dd></div>
                        </dl>
                    </section>

                    {{-- Versies --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="mb-3 text-sm font-bold text-navy-900">Versies</h2>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50/50 p-2.5 text-sm">
                                <span class="font-bold text-navy-900">v{{ $quote->version }}</span>
                                <span class="flex-1 text-gray-600">Huidige versie · {{ $quote->status->label() }}</span>
                            </div>
                            @forelse ($quote->versions->reject(fn ($version) => $version->version === $quote->version) as $version)
                                <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-2.5 text-sm">
                                    <span class="font-bold text-gray-500">v{{ $version->version }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-gray-600">{{ $version->note ?? 'Verstuurde versie' }}</span>
                                        <span class="text-xs text-gray-400">{{ $version->created_at->translatedFormat('j M Y H:i') }}{{ $version->creator ? ' · '.$version->creator->name : '' }}</span>
                                    </span>
                                    <span class="text-xs font-semibold whitespace-nowrap text-gray-500">€ {{ number_format((float) ($version->snapshot['total'] ?? 0), 0, ',', '.') }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">Nog geen eerdere versies — bij versturen wordt de inhoud bevroren als v{{ $quote->version }}.</p>
                            @endforelse
                        </div>

                        @if ($quote->status !== \App\Enums\QuoteStatus::Concept)
                            <form method="POST" action="{{ route('quotes.version', $quote) }}" class="mt-4 flex gap-2"
                                  @if ($quote->status === \App\Enums\QuoteStatus::Akkoord) onsubmit="return confirm('Deze offerte is al akkoord. Een nieuwe versie betekent dat de klant opnieuw moet tekenen. Doorgaan?');" @endif>
                                @csrf
                                <input type="text" name="note" required maxlength="255" placeholder="Wat wijzigt er in deze versie? (wijzigingslog)"
                                       class="flex-1 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <button type="submit" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Nieuwe versie</button>
                            </form>
                        @endif
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
                                        <button type="submit" name="status" value="verstuurd" class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600"><x-icon name="paper-airplane" /> Versturen (versie bevriezen)</button>
                                    </form>
                                    <p class="text-xs text-gray-400">Versturen bevriest v{{ $quote->version }} en activeert de klantlink. Daarna deel je de link met de klant.</p>
                                @else
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
                            <a href="{{ route('projects.show', $quote->project) }}" class="block rounded-xl bg-navy-950 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-navy-900">→ Naar project</a>
                        @else
                            <p class="text-sm text-gray-400">Deze offerte is afgehandeld.</p>
                        @endif
                    </section>

                    {{-- Ondertekening --}}
                    @if ($quote->signed_at)
                        <section class="rounded-2xl border border-green-200 bg-green-50 p-5">
                            <h2 class="mb-2 flex items-center gap-1.5 text-sm font-bold text-green-800"><x-icon name="check" class="h-4 w-4" /> Digitaal ondertekend</h2>
                            <p class="text-sm text-green-800">{{ $quote->signed_name }}</p>
                            <p class="text-xs text-green-700">{{ $quote->signed_at->translatedFormat('j M Y H:i') }} · versie v{{ $quote->version }} · IP {{ $quote->signed_ip }}</p>
                        </section>
                    @endif

                    {{-- Verloop --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="mb-3 text-sm font-bold text-navy-900">Verloop</h2>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="text-gray-500">Aangemaakt</dt><dd class="font-medium">{{ $quote->created_at->translatedFormat('j M Y H:i') }}</dd></div>
                            <div><dt class="text-gray-500">Verstuurd</dt><dd class="font-medium">{{ $quote->sent_at?->translatedFormat('j M Y H:i') ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500">Laatst bekeken</dt><dd class="font-medium">{{ $quote->viewed_at?->translatedFormat('j M Y H:i') ?? '—' }} @if ($quote->viewed_count > 0) ({{ $quote->viewed_count }}×) @endif</dd></div>
                            <div><dt class="text-gray-500">Geldig tot</dt><dd class="font-medium {{ $quote->isExpired() && $quote->status->isOpen() ? 'text-red-600' : '' }}">{{ $quote->valid_until?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                            @if ($quote->accepted_at)
                                <div><dt class="text-gray-500">Akkoord op</dt><dd class="font-medium text-green-700">{{ $quote->accepted_at->translatedFormat('j M Y H:i') }}</dd></div>
                            @endif
                            @if ($quote->rejected_at)
                                <div><dt class="text-gray-500">Afgewezen op</dt><dd class="font-medium text-red-700">{{ $quote->rejected_at->translatedFormat('j M Y H:i') }}</dd></div>
                            @endif
                            @if ($quote->calculation)
                                <div><dt class="text-gray-500">Calculatie</dt><dd class="font-medium"><a href="{{ route('calculations.show', $quote->calculation) }}" class="text-brand-600 hover:text-brand-500">{{ $quote->calculation->title }}</a></dd></div>
                            @endif
                        </dl>
                    </section>

                    {{-- Klant --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="mb-3 text-sm font-bold text-navy-900">Klant</h2>
                        <p class="text-sm font-semibold text-navy-900">{{ $quote->customer->name }}</p>
                        <p class="text-sm text-gray-500">{{ $quote->customer->city ?? '—' }}</p>
                        <a href="{{ route('customers.show', $quote->customer) }}" class="mt-2 inline-block text-xs font-semibold text-brand-600 hover:text-brand-500">Klantdossier →</a>
                    </section>

                    @if ($quote->status === \App\Enums\QuoteStatus::Concept)
                        <form method="POST" action="{{ route('quotes.destroy', $quote) }}" onsubmit="return confirm('Conceptofferte verwijderen?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full rounded-xl border border-red-200 py-2.5 text-sm font-semibold text-red-500 transition hover:bg-red-50">Concept verwijderen</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Tab: Teksten (blokken-editor, mockup: links blokken, rechts live document) ===== --}}
        <div x-show="tab === 'teksten'" x-cloak>
            @if ($quote->status !== \App\Enums\QuoteStatus::Concept)
                <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                    Deze versie is bevroren ({{ $quote->status->label() }}). Start een nieuwe versie via het Overzicht om de teksten te wijzigen.
                </div>
            @endif

            <div x-data="{ blocks: @js($quote->blocks ?? []) }" class="grid gap-4 xl:grid-cols-2">
                <div>
                    @if ($quote->status === \App\Enums\QuoteStatus::Concept)
                        <form method="POST" action="{{ route('quotes.template', $quote) }}" class="mb-4 flex items-center gap-2 rounded-2xl border border-gray-200 bg-white p-4"
                              onsubmit="return confirm('Template toepassen? Alle bloktekst wordt overschreven met de standaardtekst.');">
                            @csrf
                            <label for="template" class="text-sm font-semibold text-navy-900">Template</label>
                            <select name="template" id="template" class="flex-1 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Support\QuoteTemplates::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Toepassen</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('quotes.blocks', $quote) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="blocks" :value="JSON.stringify(blocks)">

                        <div class="space-y-2">
                            <template x-for="(block, index) in blocks" :key="block.key">
                                <div class="rounded-2xl border bg-white transition" :class="block.enabled ? 'border-gray-200' : 'border-gray-100 opacity-60'"
                                     x-data="{ open: false }">
                                    <div class="flex items-center gap-3 px-4 py-3">
                                        <input type="checkbox" x-model="block.enabled" @if ($quote->status !== \App\Enums\QuoteStatus::Concept) disabled @endif
                                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" title="Blok tonen in de offerte">
                                        <button type="button" @click="open = !open" class="flex flex-1 items-center justify-between gap-2 text-left">
                                            <span class="text-sm font-semibold text-navy-900" x-text="block.title"></span>
                                            <x-icon name="chevron-down" class="h-4 w-4 text-gray-400 transition" x-bind:class="open ? 'rotate-180' : ''" />
                                        </button>
                                    </div>
                                    <div x-show="open" x-cloak class="border-t border-gray-100 p-4">
                                        <textarea x-model="block.body" rows="6" @if ($quote->status !== \App\Enums\QuoteStatus::Concept) disabled @endif
                                                  class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-50"></textarea>
                                    </div>
                                </div>
                            </template>
                        </div>

                        @if ($quote->status === \App\Enums\QuoteStatus::Concept)
                            <button type="submit" class="mt-4 rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-600">Teksten opslaan</button>
                        @endif
                    </form>
                </div>

                {{-- Live document --}}
                <div class="hidden xl:block">
                    <div class="sticky top-20 max-h-[80vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                        <div class="mb-6 border-b border-gray-100 pb-4">
                            <p class="text-xs font-bold tracking-wide text-brand-500 uppercase">Live voorbeeld</p>
                            <p class="text-lg font-bold text-navy-950">Offerte {{ $quote->number }} · v{{ $quote->version }}</p>
                        </div>
                        <div class="space-y-6">
                            <template x-for="block in blocks.filter(b => b.enabled && (b.body || ['investering','stelposten'].includes(b.key)))" :key="'p'+block.key">
                                <section>
                                    <h3 class="mb-2 text-base font-bold text-navy-950" x-text="block.title"></h3>
                                    <p class="text-sm whitespace-pre-line text-gray-600" x-text="block.body"></p>
                                    <p x-show="block.key === 'investering'" class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-400">+ investeringstabel (€ {{ number_format((float) $quote->total, 2, ',', '.') }} incl. btw)</p>
                                </section>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Tab: Preview (klant) ===== --}}
        <div x-show="tab === 'preview'" x-cloak>
            <div class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="bg-navy-950 px-8 py-10 text-white">
                    <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="mb-6 h-10 w-auto">
                    <p class="text-sm text-navy-300">Offerte {{ $quote->number }} · versie v{{ $quote->version }}</p>
                    <h1 class="mt-1 text-2xl font-bold">{{ $quote->lead?->service ?? 'Renovatiewerkzaamheden' }}</h1>
                    <p class="mt-1 text-navy-100">{{ $quote->customer->name }}</p>
                </div>
                <div class="p-8">
                    @include('quotes.partials.document', ['quote' => $quote])
                </div>
            </div>
            <p class="mt-3 text-center text-xs text-gray-400">Zo ziet de klant de offerte via de klantlink (inclusief knoppen voor ondertekenen, aanpassing aanvragen en afwijzen).</p>
        </div>

    </div>

</x-layouts.app>
