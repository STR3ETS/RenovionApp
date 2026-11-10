@php
    $openStappen = $job->steps->whereNull('completed_at');
@endphp

<div x-data="{ open: false }">
    <button @click="open = true"
            class="flex w-full items-center justify-center gap-2 rounded-2xl border border-surface-600 py-4 text-base font-bold text-white transition hover:border-brand-500">
        <x-icon name="check" class="h-5 w-5" /> Klus afronden
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-navy-950/70 backdrop-blur-sm sm:items-center">
        <div @click.outside="open = false" class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl border border-surface-700 bg-surface-800 p-5 sm:max-w-lg sm:rounded-2xl">
            <h2 class="mb-1 text-base font-bold text-white">Klus afronden</h2>
            <p class="mb-4 text-sm text-slate-400">Dit levert direct de werkbon op die de projectleider controleert.</p>

            @if ($missend->isNotEmpty())
                <div class="mb-3 flex items-start gap-2.5 rounded-xl border border-red-500/25 bg-red-500/10 p-3">
                    <x-icon name="camera" class="h-5 w-5 shrink-0 text-red-300" />
                    <p class="text-sm text-slate-200">
                        <b class="text-white">Verplichte foto ontbreekt</b> bij: {{ $missend->pluck('title')->join(', ') }}.
                    </p>
                </div>
            @elseif ($openStappen->isNotEmpty())
                <div class="mb-3 flex items-start gap-2.5 rounded-xl border border-amber-500/25 bg-amber-500/10 p-3">
                    <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-300" />
                    <p class="text-sm text-slate-200">
                        <b class="text-white">{{ $openStappen->count() }} {{ $openStappen->count() === 1 ? 'stap staat' : 'stappen staan' }} nog open.</b>
                        {{ $openStappen->take(3)->pluck('title')->join(', ') }}{{ $openStappen->count() > 3 ? ' en meer' : '' }}.
                        Je kunt alsnog afronden; dat staat dan eerlijk op de werkbon.
                    </p>
                </div>
            @else
                <div class="mb-3 flex items-center gap-2.5 rounded-xl border border-green-500/25 bg-green-500/10 p-3">
                    <x-icon name="check" class="h-5 w-5 shrink-0 text-green-300" />
                    <p class="text-sm text-slate-200">Alle stappen zijn afgevinkt.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('jobs.complete', $job) }}" class="space-y-3">
                @csrf

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Gewerkte uren" name="hours">
                        <input type="number" step="0.25" min="0.25" max="24" name="hours" id="hours"
                               value="{{ old('hours', $job->estimatedHours()) }}" required
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    </x-field>

                    <x-field label="Naam klant die aftekent" name="signed_name">
                        <input type="text" name="signed_name" id="signed_name" value="{{ old('signed_name') }}"
                               placeholder="Laat leeg als niemand aanwezig is"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                </div>

                <x-field label="Gebruikt materiaal" name="materials">
                    <textarea name="materials" id="materials" rows="3"
                              placeholder="Een regel per post, bijvoorbeeld: 6 st gipsplaat"
                              class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">{{ old('materials') }}</textarea>
                </x-field>

                <x-field label="Opmerkingen voor de projectleider" name="notes">
                    <textarea name="notes" id="notes" rows="2"
                              class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
                </x-field>

                <div class="flex gap-2 pt-1">
                    <button type="button" @click="open = false"
                            class="flex-1 rounded-xl border border-surface-600 py-3 text-sm font-semibold text-slate-300">Terug</button>
                    <button type="submit" @disabled($missend->isNotEmpty())
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
                        <x-icon name="paper-airplane" class="h-4 w-4" /> Werkbon indienen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
