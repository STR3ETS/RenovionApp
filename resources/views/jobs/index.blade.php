{{-- Mijn klussen: de werkweergave voor op de bouwplaats, mobiel eerst. --}}
<x-layouts.app title="Mijn klussen">
    <x-page-header title="Mijn klussen" eyebrow="Uitvoering" category="project"
                   subtitle="Stap voor stap uitvoeren, foto's maken, melden en afronden.">
        @if ($colleagues->isNotEmpty())
            <form method="GET" class="flex items-center gap-2">
                <label for="medewerker" class="text-xs font-semibold text-slate-400">Werken als</label>
                <select name="medewerker" id="medewerker" onchange="this.form.submit()"
                        class="rounded-xl border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($colleagues as $person)
                        <option value="{{ $person->id }}" @selected($person->id === $subject->id)>
                            {{ $person->name }}, {{ $person->job_title ?: $person->role->label() }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </x-page-header>

    @php
        // Eén kaartopmaak voor alle drie de groepen.
        $card = fn (array $row, bool $dim = false) => view('jobs.partials.card', ['row' => $row, 'dim' => $dim]);
    @endphp

    <section class="mb-6">
        <h2 class="mb-3 flex items-baseline gap-2 text-sm font-bold text-white">
            Vandaag
            <span class="text-xs font-medium text-slate-500">{{ now()->translatedFormat('l j F') }}</span>
        </h2>

        @if ($vandaag->isEmpty())
            <x-empty-state icon="sun" category="project"
                           title="Vandaag geen klus ingepland"
                           subtitle="Zodra de werkvoorbereiding je inplant, staat je klus hier." />
        @else
            <div data-motion-fan class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($vandaag as $row)
                    {{ $card($row) }}
                @endforeach
            </div>
        @endif
    </section>

    @if ($komend->isNotEmpty())
        <section class="mb-6">
            <h2 class="mb-3 text-sm font-bold text-white">Komende klussen</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($komend as $row)
                    {{ $card($row) }}
                @endforeach
            </div>
        </section>
    @endif

    @if ($afgerond->isNotEmpty())
        <section>
            <h2 class="mb-3 text-sm font-bold text-white">Afgerond</h2>
            <div class="grid gap-3 opacity-60 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($afgerond as $row)
                    {{ $card($row, true) }}
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
