<x-layouts.app title="Opvolging">
    <x-page-header title="Opvolging" eyebrow="Verkoop" category="lead"
                   subtitle="Te laat bovenaan, dan wat niets gepland heeft. Een aanvraag zonder vervolgstap valt het snelst stil." />

    <div class="scrollbar-none mb-4 flex gap-2 overflow-x-auto">
        <a href="{{ route('follow-ups.index') }}"
           class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $filter === '' ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
            Alles <span class="text-xs text-slate-500">{{ $total }}</span>
        </a>
        @foreach ($labels as $key => $label)
            @continue(($counts[$key] ?? 0) === 0 && $filter !== $key)
            <a href="{{ route('follow-ups.index', ['filter' => $key]) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $filter === $key ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
                {{ $label }} <span class="text-xs text-slate-500">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    @if ($rows->isEmpty())
        <x-empty-state icon="phone" category="lead" title="Niets om op te volgen"
                       subtitle="Alle open aanvragen hebben een vervolgstap met een datum." />
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($rows as $row)
                @php($lead = $row['lead'])
                <a href="{{ route('leads.show', $lead) }}"
                   class="flex flex-wrap items-center gap-3 border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-white/5 {{ $row['key'] === 'laat' ? 'border-l-red-500' : ($row['key'] === 'niets' ? 'border-l-amber-400' : 'border-l-transparent') }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-lead-500/15 text-lead-400">
                        <x-icon name="phone" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="truncate text-sm font-semibold text-white">{{ $lead->customer?->name ?? 'Onbekende klant' }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $lead->status->badgeClasses() }}">{{ $lead->status->label() }}</span>
                            @if ($row['silent'])
                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">Stil</span>
                            @endif
                        </span>
                        <small class="mt-0.5 block truncate text-xs text-slate-400">
                            {{ $lead->service ?: 'geen dienst' }},
                            @if ($lead->last_contact_at)
                                laatste contact {{ $lead->last_contact_at->diffForHumans() }}
                            @else
                                nog geen contact geweest
                            @endif
                            @if ($lead->assignee)
                                , {{ $lead->assignee->name }}
                            @endif
                        </small>
                    </span>

                    <span class="shrink-0 text-right">
                        <b class="block text-sm font-bold {{ $row['key'] === 'laat' ? 'text-red-400' : ($row['key'] === 'niets' ? 'text-amber-300' : 'text-white') }}">
                            {{ $row['label'] }}
                        </b>
                        <small class="block text-xs text-slate-500">
                            @if ($lead->next_action_at)
                                {{ $lead->next_action ?: 'vervolgstap' }}, {{ $lead->next_action_at->translatedFormat('j M') }}
                            @else
                                zet een vervolgstap
                            @endif
                        </small>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
