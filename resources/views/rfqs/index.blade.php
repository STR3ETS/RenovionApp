@php
    use App\Models\Rfq;
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app title="Prijsaanvragen">
    <x-page-header title="Prijsaanvragen" eyebrow="Mensen en partners" category="klant"
                   subtitle="Werk uitzetten, biedingen vergelijken tegen de begroting en gunnen.">
        <a href="{{ route('rfqs.create') }}"
           class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
            <x-icon name="plus" class="h-4 w-4" /> Nieuwe aanvraag
        </a>
    </x-page-header>

    <x-kpi-row class="lg:grid-cols-[1.5fr_repeat(2,minmax(0,1fr))]">
        <x-kpi dark label="Verplichtingen" :value="$eur($commitment)" sub="gegund aan partners" />
        <x-kpi :label="$savings >= 0 ? 'Onder begroting' : 'Boven begroting'" :value="$eur(abs($savings))"
               :alert="$savings < 0" sub="over alle gunningen" />
        <x-kpi label="Uitgezet" :value="(string) ($counts[Rfq::STATUS_SENT] ?? 0)" sub="wachten op biedingen" />
    </x-kpi-row>

    <x-tabs>
        <x-tab :href="route('rfqs.index')" :active="$status === ''" :count="$counts->sum()">Alles</x-tab>
        @foreach (Rfq::STATUSES as $key => $label)
            @continue(($counts[$key] ?? 0) === 0 && $status !== $key)
            <x-tab :href="route('rfqs.index', ['status' => $key])"
                   :active="$status === $key" :count="$counts[$key] ?? 0">{{ $label }}</x-tab>
        @endforeach
    </x-tabs>

    @if ($rfqs->isEmpty())
        <x-empty-state icon="scale" category="klant" title="Nog geen prijsaanvragen"
                       subtitle="Zet een stuk werk uit bij een paar partners en vergelijk de biedingen.">
            <a href="{{ route('rfqs.create') }}" class="inline-flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white">
                <x-icon name="plus" class="h-4 w-4" /> Nieuwe aanvraag
            </a>
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($rfqs as $rfq)
                @php($beste = $rfq->bestComparableBid())
                <a href="{{ route('rfqs.show', $rfq) }}"
                   class="flex items-center gap-3 border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-white/5 {{ $rfq->isOverdue() ? 'border-l-amber-400' : 'border-l-transparent' }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-klant-500/15 text-klant-400">
                        <x-icon name="scale" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="truncate text-sm font-semibold text-white">{{ $rfq->title }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $rfq->badgeClasses() }}">{{ $rfq->statusLabel() }}</span>
                            @if ($rfq->isOverdue())
                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">Deadline voorbij</span>
                            @endif
                        </span>
                        <small class="mt-0.5 block truncate text-xs text-slate-400">
                            {{ $rfq->number }}, {{ $rfq->project->name }},
                            {{ $rfq->invited->count() }} {{ $rfq->invited->count() === 1 ? 'partij' : 'partijen' }} uitgenodigd,
                            {{ $rfq->bids->count() }} {{ $rfq->bids->count() === 1 ? 'bieding' : 'biedingen' }}
                        </small>
                    </span>

                    <span class="shrink-0 text-right">
                        <b class="block text-sm font-bold text-white">{{ $eur((float) $rfq->budget) }}</b>
                        @if ($rfq->winner)
                            <small class="block text-xs {{ ($rfq->savings() ?? 0) >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                {{ $rfq->winner->name }}, {{ $eur((float) ($rfq->winningBid()?->price ?? 0)) }}
                            </small>
                        @elseif ($beste)
                            <small class="block text-xs text-slate-500">beste bieding {{ $eur((float) $beste->price) }}</small>
                        @else
                            <small class="block text-xs text-slate-500">begroot</small>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
