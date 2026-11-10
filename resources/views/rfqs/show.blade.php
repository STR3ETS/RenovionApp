@php
    use App\Models\Rfq;
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app :title="$rfq->title">
    <div class="mb-4">
        <a href="{{ route('rfqs.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Prijsaanvragen
        </a>
    </div>

    <x-page-header :title="$rfq->title" :eyebrow="$rfq->number" category="klant"
                   :subtitle="collect([$rfq->project->name, $rfq->chapter_code ? 'hoofdstuk '.$rfq->chapter_code : null, 'begroot '.$eur((float) $rfq->budget)])->filter()->join(', ')">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $rfq->badgeClasses() }}">{{ $rfq->statusLabel() }}</span>
    </x-page-header>

    @if ($rfq->winner)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-green-500/25 bg-green-500/10 p-4">
            <x-icon name="check" class="h-6 w-6 shrink-0 text-green-300" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-white">Gegund aan {{ $rfq->winner->name }}</p>
                <p class="mt-0.5 text-sm text-slate-300">
                    {{ $eur((float) ($rfq->winningBid()?->price ?? 0)) }} tegenover {{ $eur((float) $rfq->budget) }} begroot,
                    {{ ($rfq->savings() ?? 0) >= 0 ? 'dus' : 'dus' }}
                    <b class="{{ ($rfq->savings() ?? 0) >= 0 ? 'text-green-300' : 'text-red-300' }}">
                        {{ $eur(abs($rfq->savings() ?? 0)) }} {{ ($rfq->savings() ?? 0) >= 0 ? 'onder' : 'boven' }}
                    </b>
                    de begroting. Opdracht {{ $rfq->order_number }}, {{ $rfq->awarded_on?->translatedFormat('j F Y') }}.
                </p>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Biedingen vergelijken --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Bie<span class="text-brand-500">dingen</span></h2>
                <p class="mb-4 text-xs text-slate-500">
                    Een bieding die niet alle posten prijst, is niet vergelijkbaar. Dat staat in de kolom compleet.
                </p>

                @if ($rfq->bids->isEmpty())
                    <p class="text-sm text-slate-500">Nog geen biedingen binnen.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-surface-700 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                    <th class="pb-2 text-left">Partner</th>
                                    <th class="pb-2 text-right">Prijs</th>
                                    <th class="pb-2 text-right">Tegen budget</th>
                                    <th class="pb-2 text-right">Start</th>
                                    <th class="pb-2 text-right">Compleet</th>
                                    @if ($rfq->status !== Rfq::STATUS_AWARDED && $rfq->status !== Rfq::STATUS_DONE)
                                        <th class="pb-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rfq->bids as $bid)
                                    @php($afwijking = $bid->varianceAgainstBudget())
                                    <tr class="border-b border-surface-700/60 {{ $best?->id === $bid->id ? 'bg-green-500/5' : '' }}">
                                        <td class="py-3">
                                            <span class="block font-semibold text-white">{{ $bid->subcontractor->name }}</span>
                                            @if ($bid->conditions)
                                                <span class="block text-xs text-slate-500">{{ $bid->conditions }}</span>
                                            @endif
                                            @unless ($bid->subcontractor->mayBeInvited())
                                                <span class="mt-1 inline-block rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">
                                                    {{ $bid->subcontractor->statusLabel() }}
                                                </span>
                                            @endunless
                                        </td>
                                        <td class="py-3 text-right font-bold text-white">{{ $eur((float) $bid->price) }}</td>
                                        <td class="py-3 text-right">
                                            @if ($afwijking !== null)
                                                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $afwijking > 0 ? 'bg-red-500/15 text-red-300' : 'bg-green-500/15 text-green-300' }}">
                                                    {{ $afwijking > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($afwijking, 1, ',', '.'), '0'), ',') }}%
                                                </span>
                                            @else
                                                <span class="text-xs text-slate-500">geen budget</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right text-slate-400">
                                            {{ $bid->can_start_on?->translatedFormat('j M') ?? 'onbekend' }}
                                            @if ($bid->days)
                                                <span class="block text-xs text-slate-500">{{ $bid->days }} dagen</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right">
                                            <span class="text-sm {{ $bid->isComplete() ? 'text-green-400' : 'text-amber-300' }}">{{ $bid->completeness }}%</span>
                                        </td>
                                        @if ($rfq->status !== Rfq::STATUS_AWARDED && $rfq->status !== Rfq::STATUS_DONE)
                                            <td class="py-3 pl-2 text-right">
                                                <form method="POST" action="{{ route('rfqs.award', [$rfq, $bid]) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            class="rounded-xl px-3 py-1.5 text-xs font-bold transition {{ $best?->id === $bid->id ? 'bg-brand-500 text-white hover:bg-brand-600' : 'border border-surface-600 text-slate-300 hover:text-white' }}">
                                                        Gunnen
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Bieding vastleggen --}}
                @if ($rfq->invited->isNotEmpty() && $rfq->status !== Rfq::STATUS_AWARDED)
                    <form method="POST" action="{{ route('rfq-bids.store', $rfq) }}" class="mt-4 space-y-2 border-t border-surface-700 pt-4">
                        @csrf
                        <h3 class="text-sm font-bold text-white">Bieding <span class="text-brand-500">vastleggen</span></h3>

                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <select name="subcontractor_id" required
                                    class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($rfq->invited as $partner)
                                    <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" min="0" name="price" required placeholder="Prijs"
                                   class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                            <input type="date" name="can_start_on" title="Kan starten op"
                                   class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            <input type="number" min="0" max="100" name="completeness" value="100" title="Hoeveel procent van de posten is geprijsd"
                                   class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        <input type="text" name="conditions" placeholder="Voorwaarden, bijvoorbeeld: exclusief steiger en afvoer"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">

                        <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">Vastleggen</button>
                    </form>
                @endif
            </div>

            @if ($rfq->scope)
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-2 text-sm font-bold text-white">Werkomschrijving</h2>
                    <p class="text-sm whitespace-pre-line text-slate-300">{{ $rfq->scope }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Uitgenodigde partijen --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Uitge<span class="text-brand-500">nodigd</span></h2>

                @if ($rfq->invited->isEmpty())
                    <p class="mb-3 text-sm text-slate-500">Nog niemand uitgenodigd.</p>
                @else
                    <div class="mb-3 space-y-1.5">
                        @foreach ($rfq->invited as $partner)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="min-w-0 flex-1 truncate text-slate-200">{{ $partner->name }}</span>
                                @if ($rfq->bids->contains('subcontractor_id', $partner->id))
                                    <span class="shrink-0 rounded-full bg-green-500/15 px-2 py-0.5 text-[10px] font-bold text-green-300">Bood</span>
                                @else
                                    <span class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-400">Wacht</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($candidates->isNotEmpty() && $rfq->status !== Rfq::STATUS_AWARDED)
                    <form method="POST" action="{{ route('rfqs.invite', $rfq) }}" class="space-y-2 border-t border-surface-700 pt-3">
                        @csrf
                        <p class="text-xs text-slate-500">Partijen met een rond dossier kun je uitnodigen.</p>

                        <div class="max-h-56 space-y-1 overflow-y-auto">
                            @foreach ($candidates as $partner)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-white/5">
                                    <input type="checkbox" name="subcontractor_ids[]" value="{{ $partner->id }}"
                                           class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                                    <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ $partner->name }}</span>
                                    @unless ($partner->mayBeInvited())
                                        <span class="shrink-0 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold text-amber-300" title="{{ $partner->statusLabel() }}">!</span>
                                    @endunless
                                </label>
                            @endforeach
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Uitzetten</button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ route('rfqs.update', $rfq) }}" class="space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-bold text-white">Aanvraag</h2>

                <x-field label="Budget uit de begroting" name="budget">
                    <input type="number" step="0.01" name="budget" value="{{ $rfq->budget }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Deadline voor biedingen" name="deadline">
                    <input type="date" name="deadline" value="{{ $rfq->deadline?->toDateString() }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Gewenste start" name="starts_on">
                    <input type="date" name="starts_on" value="{{ $rfq->starts_on?->toDateString() }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Werkomschrijving" name="scope">
                    <textarea name="scope" rows="4"
                              class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">{{ $rfq->scope }}</textarea>
                </x-field>

                <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Opslaan</button>
            </form>
        </div>
    </div>
</x-layouts.app>
