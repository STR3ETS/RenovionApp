@php
    use App\Models\Subcontractor;
@endphp

<x-layouts.app title="Onderaannemers">
    <x-page-header title="Onderaannemers" eyebrow="Mensen en partners" category="klant"
                   subtitle="Zolang het dossier niet rond is, krijgt een partij geen uitnodiging en geen opdracht.">
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.outside="open = false"
                    class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                <x-icon name="plus" class="h-4 w-4" /> Partner aanmelden
            </button>

            <form x-show="open" x-cloak method="POST" action="{{ route('subcontractors.store') }}"
                  class="absolute right-0 z-20 mt-2 w-80 space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-4 shadow-2xl shadow-black/40">
                @csrf
                <x-field label="Bedrijfsnaam" name="name">
                    <input type="text" name="name" id="name" required
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Contactpersoon" name="contact_name">
                    <input type="text" name="contact_name" id="contact_name"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="E-mailadres" name="email">
                    <input type="email" name="email" id="email"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Plaats" name="city">
                    <input type="text" name="city" id="city"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white">Aanmelden</button>
            </form>
        </div>
    </x-page-header>

    <form method="GET" class="scrollbar-none mb-4 flex gap-2 overflow-x-auto">
        <div class="relative min-w-0 flex-1 sm:max-w-xs">
            <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op naam of plaats"
                   class="w-full rounded-full border-surface-700 bg-surface-850 py-2 pl-10 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <select name="vak" onchange="this.form.submit()"
                class="shrink-0 rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
            <option value="">Alle vakken</option>
            @foreach ($trades as $vak)
                <option value="{{ $vak }}" @selected($trade === $vak)>{{ ucfirst($vak) }}</option>
            @endforeach
        </select>
    </form>

    <div class="scrollbar-none mb-4 flex gap-2 overflow-x-auto">
        <a href="{{ route('subcontractors.index', ['q' => $zoek ?: null, 'vak' => $trade ?: null]) }}"
           class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $status === '' ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
            Alles <span class="text-xs text-slate-500">{{ $total }}</span>
        </a>
        @foreach ([Subcontractor::STATUS_APPROVED => 'Goedgekeurd', Subcontractor::STATUS_INCOMPLETE => 'Dossier incompleet', Subcontractor::STATUS_EXPIRED => 'Document verlopen', Subcontractor::STATUS_BLOCKED => 'Geblokkeerd'] as $key => $label)
            @continue(($counts[$key] ?? 0) === 0 && $status !== $key)
            <a href="{{ route('subcontractors.index', ['status' => $key, 'q' => $zoek ?: null, 'vak' => $trade ?: null]) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $status === $key ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
                {{ $label }} <span class="text-xs text-slate-500">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    @if ($subcontractors->isEmpty())
        <x-empty-state icon="briefcase" category="klant" title="Nog geen onderaannemers"
                       subtitle="Meld een partner aan en vul het dossier aan voordat je werk uitzet." />
    @else
        <div data-motion-fan class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($subcontractors as $sub)
                @php($dossier = $sub->dossier())
                <a href="{{ route('subcontractors.show', $sub) }}"
                   class="motion-lift block overflow-hidden rounded-2xl border border-surface-700 bg-surface-800 transition hover:border-surface-600">
                    <span class="block h-[3px] w-full {{ $sub->status() === Subcontractor::STATUS_APPROVED ? 'bg-green-500' : ($sub->status() === Subcontractor::STATUS_INCOMPLETE ? 'bg-amber-500' : 'bg-red-500') }}"></span>

                    <span class="block p-4">
                        <span class="mb-2 flex items-start justify-between gap-2">
                            <b class="min-w-0 truncate text-sm font-bold text-white">{{ $sub->name }}</b>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $sub->badgeClasses() }}">{{ $sub->statusLabel() }}</span>
                        </span>

                        <span class="block truncate text-xs text-slate-400">
                            {{ collect([$sub->contact_name, $sub->city])->filter()->join(', ') ?: 'geen contactgegevens' }}
                        </span>

                        @if (filled($sub->trades))
                            <span class="mt-2 flex flex-wrap gap-1">
                                @foreach (array_slice($sub->trades, 0, 4) as $vak)
                                    <span class="rounded bg-surface-700 px-1.5 py-0.5 text-[10px] font-semibold text-slate-300">{{ $vak }}</span>
                                @endforeach
                            </span>
                        @endif

                        <span class="mt-3 flex items-center justify-between gap-2 border-t border-surface-700 pt-3">
                            <span class="text-xs {{ $dossier['complete'] ? 'text-green-400' : 'text-amber-300' }}">
                                {{ $dossier['complete'] ? 'Dossier rond' : count($dossier['open']).' '.(count($dossier['open']) === 1 ? 'punt' : 'punten').' open' }}
                            </span>
                            <span class="text-xs text-slate-500">
                                {{ $sub->assignments }} {{ $sub->assignments === 1 ? 'opdracht' : 'opdrachten' }}
                                @if ($sub->rating)
                                    , {{ $sub->rating }} van 5
                                @endif
                            </span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
