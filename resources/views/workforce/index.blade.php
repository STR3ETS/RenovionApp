@php
    use App\Models\Absence;
    use App\Support\CertificateCatalogue;
@endphp

<x-layouts.app title="Eigen mensen">
    <x-page-header title="Eigen mensen" eyebrow="Mensen en partners" category="klant"
                   subtitle="Wie we hebben, met welke certificaten, hoe druk ze zijn en wanneer ze er niet zijn.">
        <div class="flex items-center gap-2">
            <a href="{{ route('workforce.index', ['week' => $week->copy()->subWeek()->toDateString()]) }}"
               class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:text-white">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </a>
            <span class="text-sm font-semibold text-white">
                Week {{ $week->isoWeek() }}, {{ $week->translatedFormat('j M') }} t/m {{ $weekEnd->translatedFormat('j M') }}
            </span>
            <a href="{{ route('workforce.index', ['week' => $week->copy()->addWeek()->toDateString()]) }}"
               class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:text-white">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </div>
    </x-page-header>

    @if ($expiringTotal > 0)
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
            <x-icon name="shield-check" class="h-5 w-5 shrink-0 text-amber-300" />
            <p class="text-sm text-slate-200">
                <b class="text-white">{{ $expiringTotal }} {{ $expiringTotal === 1 ? 'certificaat verloopt' : 'certificaten verlopen' }} binnen dertig dagen of is al verlopen.</b>
                Wie zonder geldig certificaat de bouwplaats op gaat, is een risico.
            </p>
        </div>
    @endif

    @if ($rows->isEmpty())
        <x-empty-state icon="identification" category="klant" title="Nog geen mensen"
                       subtitle="Voeg je team toe via Organisatie, dan verschijnen ze hier met capaciteit en certificaten." />
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php
                    $person = $row['person'];
                    $load = $row['load'];
                    $bezetting = min(100, $load['percentage']);
                @endphp

                <div x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                    <button @click="open = !open" class="flex w-full flex-wrap items-center gap-4 p-4 text-left transition hover:bg-white/5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-klant-500/15 text-sm font-bold text-klant-300">
                            {{ str($person->name)->substr(0, 1)->upper() }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <b class="truncate text-sm font-bold text-white">{{ $person->name }}</b>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $person->role->badgeClasses() }}">
                                    {{ $person->job_title ?: $person->role->label() }}
                                </span>
                                @if (! $person->isPlannable())
                                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-400">Niet planbaar</span>
                                @endif
                            </span>

                            <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                @foreach ($person->certificates as $cert)
                                    @php($meta = $cert->meta())
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-bold {{ $cert->state() === 'ok' ? CertificateCatalogue::chipClasses($meta['colour']) : CertificateCatalogue::stateClasses($cert->state()) }}"
                                          title="{{ $cert->type }}{{ $cert->valid_until ? ', geldig tot '.$cert->valid_until->translatedFormat('j M Y') : '' }}">
                                        {{ $meta['short'] }}
                                    </span>
                                @endforeach
                                @if ($person->certificates->isEmpty())
                                    <span class="text-xs text-slate-500">Geen certificaten vastgelegd</span>
                                @endif
                            </span>
                        </span>

                        <span class="shrink-0 text-right">
                            <span class="block text-sm font-bold {{ $bezetting > 100 ? 'text-red-400' : ($bezetting >= 80 ? 'text-white' : 'text-slate-300') }}">
                                {{ $load['planned'] }} van {{ $load['available'] }} uur
                            </span>
                            <span class="mt-1 block h-1.5 w-28 overflow-hidden rounded-full bg-surface-700">
                                <span class="block h-full rounded-full {{ $load['percentage'] > 100 ? 'bg-red-500' : ($load['percentage'] >= 80 ? 'bg-green-500' : 'bg-brand-500') }}"
                                      style="width: {{ $bezetting }}%"></span>
                            </span>
                            <span class="mt-0.5 block text-xs text-slate-500">
                                {{ $load['percentage'] }}% bezet @if ($load['absent'] > 0), {{ $load['absent'] }} dagen weg @endif
                            </span>
                        </span>
                    </button>

                    <div x-show="open" x-cloak class="border-t border-surface-700 bg-surface-850 p-4">
                        <div class="grid gap-4 lg:grid-cols-3">
                            {{-- Deze week --}}
                            <div>
                                <p class="mb-2 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Deze week</p>
                                @if ($row['entries']->isEmpty())
                                    <p class="text-sm text-slate-500">Niets ingepland.</p>
                                @else
                                    <div class="space-y-1.5">
                                        @foreach ($row['entries'] as $entry)
                                            <div class="text-sm">
                                                <span class="block truncate text-slate-200">{{ $entry->displayTitle() }}</span>
                                                <span class="block text-xs text-slate-500">
                                                    {{ $entry->project?->name }},
                                                    {{ $entry->date->translatedFormat('j M') }}@if ($entry->end_date && ! $entry->end_date->isSameDay($entry->date)) t/m {{ $entry->end_date->translatedFormat('j M') }}@endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Certificaten --}}
                            <div>
                                <p class="mb-2 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Certificaten</p>
                                <div class="mb-2 space-y-1.5">
                                    @foreach ($person->certificates as $cert)
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="min-w-0 flex-1 truncate text-slate-200">{{ $cert->type }}</span>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ CertificateCatalogue::stateClasses($cert->state()) }}">
                                                {{ $cert->valid_until?->translatedFormat('M Y') ?? 'onbeperkt' }}
                                            </span>
                                            @can('manage-team')
                                                <form method="POST" action="{{ route('certificates.destroy', $cert) }}" class="shrink-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-slate-600 transition hover:text-red-400"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                                </form>
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>

                                @can('manage-team')
                                    <form method="POST" action="{{ route('certificates.store', $person) }}" class="space-y-2">
                                        @csrf
                                        <select name="type" required
                                                class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                            @foreach ($certificateTypes as $type => $meta)
                                                <option value="{{ $type }}">{{ $type }}, {{ $meta['about'] }}</option>
                                            @endforeach
                                        </select>
                                        <div class="flex gap-2">
                                            <input type="date" name="issued_on" title="Afgegeven op"
                                                   class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                            <button type="submit" class="shrink-0 rounded-xl bg-brand-500 px-3 py-2 text-white transition hover:bg-brand-600">
                                                <x-icon name="plus" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </form>
                                @endcan
                            </div>

                            {{-- Afwezigheid --}}
                            <div>
                                <p class="mb-2 text-[11px] font-bold tracking-widest text-slate-500 uppercase">Afwezig</p>
                                <div class="mb-2 space-y-1.5">
                                    @forelse ($person->absences as $absence)
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="min-w-0 flex-1 truncate text-slate-200">
                                                {{ $absence->typeLabel() }},
                                                {{ $absence->from->translatedFormat('j M') }} t/m {{ $absence->until->translatedFormat('j M') }}
                                            </span>
                                            <form method="POST" action="{{ route('absences.destroy', $absence) }}" class="shrink-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-slate-600 transition hover:text-red-400"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                            </form>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">Geen afwezigheid gepland.</p>
                                    @endforelse
                                </div>

                                <form method="POST" action="{{ route('absences.store', $person) }}" class="space-y-2">
                                    @csrf
                                    <select name="type" required
                                            class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                        @foreach (Absence::TYPES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex gap-2">
                                        <input type="date" name="from" required title="Van"
                                               class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                        <input type="date" name="until" required title="Tot en met"
                                               class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                        <button type="submit" class="shrink-0 rounded-xl bg-brand-500 px-3 py-2 text-white transition hover:bg-brand-600">
                                            <x-icon name="plus" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        @can('manage-team')
                            <form method="POST" action="{{ route('workforce.update', $person) }}" class="mt-4 grid gap-3 border-t border-surface-700 pt-4 sm:grid-cols-4">
                                @csrf
                                @method('PATCH')
                                <x-field label="Functie" name="job_title">
                                    <input type="text" name="job_title" value="{{ $person->job_title }}"
                                           class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                </x-field>
                                <x-field label="Uurtarief" name="hourly_rate">
                                    <input type="number" step="0.01" name="hourly_rate" value="{{ $person->hourly_rate }}"
                                           class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                </x-field>
                                <x-field label="Uren per week" name="weekly_capacity">
                                    <input type="number" min="0" max="80" name="weekly_capacity" value="{{ $person->weekly_capacity }}"
                                           class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                </x-field>
                                <div class="flex items-end gap-2">
                                    <label class="flex items-center gap-2 text-sm text-slate-300">
                                        <input type="checkbox" name="plannable" value="1" @checked($person->plannable)
                                               class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                                        Planbaar
                                    </label>
                                    <button type="submit" class="ml-auto rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">Opslaan</button>
                                </div>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
