@php
    $query = fn (array $extra) => array_filter([
        'weergave' => $view,
        'datum' => $date->toDateString(),
        'medewerker' => $view !== 'team' && $subject->id !== auth()->id() ? $subject->id : null,
        ...$extra,
    ]);

    $periode = match ($view) {
        'dag' => $date->translatedFormat('l j F Y'),
        default => 'Week '.$from->isoWeek.', '.$from->translatedFormat('j M').' t/m '.$until->translatedFormat('j M Y'),
    };

    // Wat geen tijd heeft, of buiten het raster valt, staat bovenaan de dag.
    $firstHour = $hours[0];
    $lastHour = $hours[count($hours) - 1];
    $onGrid = fn (array $item) => $item['start'] !== null
        && (int) substr($item['start'], 0, 2) >= $firstHour
        && (int) substr($item['start'], 0, 2) <= $lastHour;
@endphp

<x-layouts.app title="Agenda">
    <div x-data="{
            open: false, tab: 'afspraak', date: @js($date->toDateString()), time: '', user: @js((string) $subject->id), lead: '',
            nieuw(date, time, user) { this.tab = @js($canCrm ? 'afspraak' : 'taak'); this.date = date; this.time = time; if (user) this.user = String(user); this.lead = ''; this.open = true },
            bel(lead) { this.tab = 'belpoging'; this.lead = String(lead); this.open = true },
         }"
         @keydown.escape.window="open = false">

        <x-page-header title="Agenda" :subtitle="ucfirst($periode)">
            <a href="{{ route('agenda.index', $query(['datum' => $date->copy()->subDays($step)->toDateString()])) }}"
               class="flex h-10 w-10 items-center justify-center rounded-full border border-surface-600 text-slate-300 transition hover:text-white" aria-label="Vorige periode">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </a>
            <a href="{{ route('agenda.index', $query(['datum' => null])) }}"
               class="rounded-full border border-surface-600 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:text-white">Vandaag</a>
            <a href="{{ route('agenda.index', $query(['datum' => $date->copy()->addDays($step)->toDateString()])) }}"
               class="flex h-10 w-10 items-center justify-center rounded-full border border-surface-600 text-slate-300 transition hover:text-white" aria-label="Volgende periode">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </a>

            @if ($canCrm && $view !== 'team')
                <form method="GET" action="{{ route('agenda.index') }}">
                    <input type="hidden" name="weergave" value="{{ $view }}">
                    <input type="hidden" name="datum" value="{{ $date->toDateString() }}">
                    <select name="medewerker" onchange="this.form.submit()" aria-label="Medewerker"
                            class="rounded-full border-surface-700 bg-surface-850 py-2.5 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($team as $member)
                            <option value="{{ $member->id }}" @selected($member->id === $subject->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <button type="button" @click="nieuw(@js($date->toDateString()), '', @js($subject->id))"
                    class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                <x-icon name="plus" class="h-4 w-4" /> Nieuw
            </button>
        </x-page-header>

        <x-tabs>
            @foreach ($views as $key => $label)
                <x-tab :href="route('agenda.index', $query(['weergave' => $key, 'medewerker' => $key !== 'team' && $subject->id !== auth()->id() ? $subject->id : null]))"
                       :active="$view === $key">{{ $label }}</x-tab>
            @endforeach
        </x-tabs>

        <div class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-lead-400"></span> Afspraak</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-calc-400"></span> Taak</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-klant-400"></span> Bellen en opvolgen</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-project-400"></span> Werk uit de planning</span>
        </div>

        @if ($view === 'team')
            @include('agenda.partials.team')
        @else
            @include('agenda.partials.grid')
        @endif

        @include('agenda.partials.new')
    </div>
</x-layouts.app>
