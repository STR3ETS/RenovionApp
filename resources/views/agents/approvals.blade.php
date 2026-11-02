@php
    use App\Support\AgentRegistry;
@endphp

<x-layouts.app title="Goedkeuringen">
    <x-page-header title="Goedkeuringen" eyebrow="Nova en agents" category="calc"
                   subtitle="Een agent stelt voor, een mens beslist. Verandert het voorstel na het verzoek, dan vervalt de goedkeuring." />

    @if ($open->isEmpty())
        <x-empty-state icon="hand-thumb-up" category="calc" title="Niets te besluiten"
                       subtitle="Er liggen geen voorstellen die op jou wachten." />
    @else
        <div class="mb-6 space-y-3">
            @foreach ($open as $approval)
                @php($agent = $approval->agentMeta())
                <div class="overflow-hidden rounded-2xl border border-amber-500/25 bg-amber-500/5">
                    <div class="p-5">
                        <div class="flex flex-wrap items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ AgentRegistry::chipClasses($agent['colour']) }}">
                                {{ str($agent['name'])->substr(0, 1) }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <b class="text-sm font-bold text-white">{{ $approval->action }}</b>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ AgentRegistry::chipClasses($agent['colour']) }}">
                                        {{ $agent['name'] }}
                                    </span>
                                    @if ($approval->waitingDays() >= 2)
                                        <span class="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">
                                            {{ $approval->waitingDays() }} dagen onbesloten
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-1 text-sm text-slate-200">{{ $approval->summary }}</p>

                                <p class="mt-1.5 text-xs text-slate-500">
                                    {{ $approval->reference }},
                                    {{ AgentRegistry::skill($approval->skill)['name'] ?? $approval->skill }}
                                    @if ($approval->project), {{ $approval->project->name }} @endif
                                    @if ($approval->approver_role), beslist door {{ $approval->approver_role }} @endif
                                    , gevraagd {{ $approval->created_at->diffForHumans() }}
                                </p>

                                @if (filled($approval->payload))
                                    <dl class="mt-3 space-y-1 rounded-xl bg-surface-800 p-3 text-xs">
                                        @foreach ($approval->payload as $key => $value)
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-slate-400">{{ ucfirst(str_replace('_', ' ', (string) $key)) }}</dt>
                                                <dd class="text-right text-slate-200">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                        </div>

                        @can('manage-crm')
                            <form method="POST" action="{{ route('approvals.decide', $approval) }}" class="mt-4 flex flex-wrap items-center gap-2 border-t border-surface-700 pt-4">
                                @csrf
                                <input type="text" name="reden" placeholder="Reden, als je afwijst"
                                       class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                                <button type="submit" name="besluit" value="afwijzen"
                                        class="shrink-0 rounded-xl border border-surface-600 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:text-white">
                                    Eerst aanpassen
                                </button>
                                <button type="submit" name="besluit" value="goedkeuren"
                                        class="flex shrink-0 items-center gap-1.5 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                                    <x-icon name="check" class="h-4 w-4" /> Ja, doe maar
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($decided->isNotEmpty())
        <h2 class="mb-3 text-sm font-bold text-white">Eerder <span class="text-brand-500">besloten</span></h2>
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($decided as $approval)
                <div class="flex flex-wrap items-center gap-3 border-b border-surface-700 p-4 last:border-b-0">
                    <span class="min-w-0 flex-1">
                        <b class="block truncate text-sm font-semibold text-white">{{ $approval->action }}</b>
                        <small class="block truncate text-xs text-slate-400">
                            {{ $approval->summary }}
                        </small>
                        <small class="block text-xs text-slate-500">
                            {{ $approval->decider?->name ?? 'het systeem' }},
                            {{ $approval->decided_at?->translatedFormat('j M Y H:i') }}
                            @if ($approval->reason), {{ $approval->reason }} @endif
                        </small>
                    </span>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $approval->badgeClasses() }}">{{ $approval->statusLabel() }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
