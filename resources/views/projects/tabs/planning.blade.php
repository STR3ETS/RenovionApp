{{-- Alles wat er op dit project is ingepland, nieuw bovenaan gegroepeerd per week. --}}
@if ($entries->isEmpty())
    <x-empty-state icon="calendar" category="planning" title="Nog niets ingepland"
                   subtitle="Plan werk in vanaf de planning, dan verschijnt het hier en bij de uitvoerder op zijn telefoon." />
@else
    <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
        @foreach ($entries as $entry)
            @php($einde = $entry->end_date ?? $entry->date)
            <div class="flex flex-wrap items-center gap-3 border-b border-surface-700 border-l-4 p-4 last:border-b-0 {{ $entry->date->isToday() ? 'border-l-brand-500' : 'border-l-transparent' }}">
                <span class="w-24 shrink-0">
                    <span class="block text-sm font-bold text-white">{{ $entry->date->translatedFormat('j M') }}</span>
                    <span class="block text-xs text-slate-500">{{ $entry->date->translatedFormat('D') }}</span>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-white">{{ $entry->displayTitle() }}</span>
                    <span class="block truncate text-xs text-slate-400">
                        {{ $entry->user?->name ?? 'niemand toegewezen' }},
                        {{ $entry->type->label() }}
                        @unless ($einde->isSameDay($entry->date))
                            , tot en met {{ $einde->translatedFormat('j M') }}
                        @endunless
                    </span>
                </span>

                <a href="{{ route('jobs.show', $entry) }}"
                   class="shrink-0 rounded-full border border-surface-600 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:text-white">
                    Klus
                </a>
            </div>
        @endforeach
    </div>
@endif
