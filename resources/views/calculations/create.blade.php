<x-layouts.app title="Nieuwe calculatie">

    <x-page-header title="Nieuwe calculatie" subtitle="Maak een calculatie op basis van een opname, omschrijving of AI-voorstel." />

    <form method="POST" action="{{ route('calculations.store') }}"
          x-data="calcCreate(@js(route('calculations.propose')), @js(old('description', $lead?->description)), @js($sources->pluck('source')->values()))"
          class="grid gap-4 lg:grid-cols-3">
        @csrf
        @if ($lead)
            <input type="hidden" name="lead_id" value="{{ $lead->id }}">
        @endif

        <div class="space-y-4 lg:col-span-2">
            {{-- Basis --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Titel *" name="title" class="sm:col-span-2">
                        <input type="text" name="title" id="title" required
                               value="{{ old('title', $lead ? 'Calculatie '.($lead->service ?? 'renovatie').' — '.$lead->customer->name : '') }}"
                               placeholder="Bijv. Calculatie badkamer — Familie Jansen"
                               class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Klant" name="customer_id" class="sm:col-span-2">
                        <select name="customer_id" id="customer_id" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Geen klant (losse calculatie) —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id', $lead?->customer_id) == $customer->id)>{{ $customer->name }} ({{ $customer->city ?? '—' }})</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>
            </section>

            {{-- Invoermethode (mockup §18) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-4 grid grid-cols-3 gap-2 text-sm font-semibold">
                    <button type="button" @click="mode = 'handmatig'"
                            :class="mode === 'handmatig' ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="rounded-xl px-3 py-2.5 transition">Handmatig</button>
                    <button type="button" @click="mode = 'ai'"
                            :class="mode === 'ai' ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="rounded-xl px-3 py-2.5 transition">AI (spraak/tekst)</button>
                    <button type="button" disabled title="PDF-import volgt in een volgende versie"
                            class="cursor-not-allowed rounded-xl bg-gray-50 px-3 py-2.5 text-gray-300">PDF import</button>
                </div>

                <div class="relative">
                    <textarea name="description" id="description" rows="4" x-model="description"
                              placeholder="Beschrijf het project… Bijv. 'Badkamer 6 m2 volledig renoveren: strippen, leidingwerk verleggen, vloer- en wandtegels, inloopdouche, wandcloset, stucwerk plafond, elektra met spots.'"
                              class="w-full rounded-xl border-gray-300 pr-12 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                    <button type="button" @click="toggleMic()" x-show="speechSupported"
                            :class="listening ? 'bg-red-500 text-white animate-pulse' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                            class="absolute right-2.5 bottom-2.5 flex h-9 w-9 items-center justify-center rounded-full transition"
                            :title="listening ? 'Stop met luisteren' : 'Spreek de opname in'">
                        <x-icon name="microphone" class="h-4.5 w-4.5" />
                    </button>
                </div>

                {{-- Handmatig --}}
                <div x-show="mode === 'handmatig'" class="mt-4">
                    <p class="mb-3 text-xs text-gray-400">Je maakt een calculatie aan en voegt daarna zelf regels toe vanuit de prijsbibliotheek.</p>
                    <button type="submit" class="rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-600">Calculatie aanmaken</button>
                </div>

                {{-- AI --}}
                <div x-show="mode === 'ai'" x-cloak class="mt-4">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="propose()" :disabled="busy || !description.trim()"
                                class="flex items-center gap-2 rounded-xl bg-navy-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-navy-900 disabled:opacity-40">
                            <x-nova-avatar class="h-5 w-5" />
                            <span x-text="busy ? 'Nova rekent…' : (proposal ? 'Opnieuw voorstellen' : 'Stel regels voor met AI')"></span>
                        </button>
                        <span x-show="busy" class="inline-block h-2 w-2 animate-pulse rounded-full bg-brand-500"></span>
                    </div>
                    <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm font-medium text-red-600"></p>

                    {{-- Voorstel: regels altijd eerst tonen vóór bevestiging (briefing §5) --}}
                    <div x-show="proposal" x-cloak class="mt-4 rounded-xl border border-brand-200 bg-brand-50/50">
                        <div class="flex items-center gap-2 border-b border-brand-100 px-4 py-3">
                            <x-nova-avatar class="h-6 w-6" />
                            <p class="text-sm font-semibold text-navy-900">Voorstel van Nova — controleer de regels voordat je bevestigt</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-bold text-gray-500 uppercase">
                                        <th class="px-4 py-2">Type</th>
                                        <th class="px-4 py-2">Omschrijving</th>
                                        <th class="px-4 py-2 text-right">Aantal</th>
                                        <th class="px-4 py-2">Eenheid</th>
                                        <th class="px-4 py-2 text-right">Prijs</th>
                                        <th class="px-4 py-2 text-right">Totaal</th>
                                        <th class="px-2 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(line, index) in proposal?.lines ?? []" :key="index">
                                        <tr class="border-t border-brand-100">
                                            <td class="px-4 py-2 text-xs font-semibold text-gray-500 capitalize" x-text="line.type"></td>
                                            <td class="px-4 py-2 font-medium text-navy-900" x-text="line.description"></td>
                                            <td class="px-4 py-2 text-right" x-text="line.quantity"></td>
                                            <td class="px-4 py-2 text-gray-500" x-text="line.unit ?? '—'"></td>
                                            <td class="px-4 py-2 text-right" x-text="'€ ' + euro(line.unit_price)"></td>
                                            <td class="px-4 py-2 text-right font-semibold" x-text="'€ ' + euro(lineTotal(line))"></td>
                                            <td class="px-2 py-2">
                                                <button type="button" @click="removeLine(index)" class="rounded p-1 text-gray-300 transition hover:text-red-500" title="Regel weglaten">
                                                    <x-icon name="x-mark" class="h-3.5 w-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-brand-200">
                                        <td colspan="5" class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 uppercase">Subtotaal (excl. onvoorzien/marge/btw)</td>
                                        <td class="px-4 py-2.5 text-right font-bold text-navy-900" x-text="'€ ' + euro(proposalTotal)"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p x-show="proposal?.note" class="border-t border-brand-100 px-4 py-3 text-xs text-gray-500">
                            <span class="font-semibold text-navy-900">Aannames:</span> <span x-text="proposal?.note"></span>
                        </p>
                    </div>

                    <template x-if="proposal">
                        <input type="hidden" name="lines" :value="JSON.stringify(proposal.lines)">
                    </template>

                    <button type="submit" x-show="proposal" x-cloak
                            class="mt-4 rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                        Calculatie aanmaken met <span x-text="proposal?.lines.length"></span> regels
                    </button>
                </div>
            </section>
        </div>

        {{-- Kostendatabase (mockup §18: bron-toggles) --}}
        <div>
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-1 text-sm font-bold text-navy-900">Geselecteerde kostendatabase</h2>
                <p class="mb-4 text-xs text-gray-400">Nova gebruikt alleen prijzen uit de ingeschakelde bronnen.</p>

                <div class="space-y-3">
                    @foreach ($sources as $source)
                        <label class="flex cursor-pointer items-center justify-between gap-3">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-navy-900">{{ $source->source }}</span>
                                <span class="block text-xs text-gray-400">Editie {{ $source->edition }}</span>
                            </span>
                            <input type="checkbox" class="peer sr-only" :checked="sources.includes(@js($source->source))" @change="toggleSource(@js($source->source))">
                            <span class="relative h-5 w-9 shrink-0 rounded-full bg-gray-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4"></span>
                        </label>
                    @endforeach

                    @foreach (['Archidat Bouwkostenwijzer Woningbouw', 'Archidat Utiliteitsbouw', '(Her)bouwkosten Woningen (taxatie)'] as $pending)
                        @continue($sources->contains('source', $pending))
                        <div class="flex items-center justify-between gap-3 opacity-50" title="Wordt geladen zodra de Archidat-licentie rond is">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-500">{{ $pending }}</span>
                                <span class="block text-xs text-gray-400">Nog niet geladen (licentie vereist)</span>
                            </span>
                            <span class="relative h-5 w-9 shrink-0 rounded-full bg-gray-100 after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow"></span>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($lead)
                <section class="mt-4 rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-2 text-sm font-bold text-navy-900">Gekoppelde aanvraag</h2>
                    <p class="text-sm font-medium text-navy-900">{{ $lead->customer->name }}</p>
                    <p class="text-xs text-gray-500">{{ $lead->service ?? 'Aanvraag' }} · {{ $lead->customer->city ?? '—' }}</p>
                </section>
            @endif
        </div>
    </form>

</x-layouts.app>
