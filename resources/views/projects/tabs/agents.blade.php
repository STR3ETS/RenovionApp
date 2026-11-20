@php
    use App\Support\AgentRegistry;
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        {{-- Wat agents op dit project deden --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-4 text-sm font-bold text-ink">Uit<span class="text-brand-500">voeringen</span></h2>

            @if ($runs->isEmpty())
                <p class="text-sm text-faint">Nog geen agent heeft iets op dit project gedaan.</p>
            @else
                <div class="space-y-2">
                    @foreach ($runs as $run)
                        @php($agent = $run->agentMeta())
                        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ AgentRegistry::chipClasses($agent['colour']) }}">
                                {{ str($agent['name'])->substr(0, 1) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <b class="truncate text-sm font-semibold text-ink">{{ $run->skillName() }}</b>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $run->badgeClasses() }}">{{ $run->statusLabel() }}</span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-muted">
                                    {{ $run->reference }}, {{ $agent['name'] }}, {{ $run->created_at->diffForHumans() }}
                                </span>
                                @if ($run->note)
                                    <span class="mt-1 block text-xs text-muted">{{ $run->note }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- De audit van dit project --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-ink">Audit</h2>
            <p class="mb-4 text-xs text-faint">Wie deed wat: een agent of een mens.</p>

            @if ($auditEvents->isEmpty())
                <p class="text-sm text-faint">Nog niets vastgelegd.</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($auditEvents as $event)
                        <div class="flex items-start gap-3 border-l-2 py-1.5 pl-3 {{ $event->isAgent() ? 'border-calc-500/60' : 'border-brand-500/60' }}">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <b class="text-sm font-semibold text-ink">{{ $event->actorName() }}</b>
                                    <span class="rounded bg-surface-700 px-1.5 py-0.5 font-mono text-[10px] text-muted">{{ $event->type }}</span>
                                    @unless ($event->isAgent())
                                        <span class="rounded-full bg-brand-500/15 px-2 py-0.5 text-[10px] font-bold text-brand-300">Mens</span>
                                    @endunless
                                </span>
                                <span class="block text-xs text-faint">{{ $event->occurred_at->translatedFormat('j M Y H:i:s') }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Besluiten op dit project --}}
    <div>
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-ink">Goed<span class="text-brand-500">keuringen</span></h2>
            <p class="mb-4 text-xs text-faint">Een agent stelt voor, een mens beslist.</p>

            @if ($approvals->isEmpty())
                <p class="text-sm text-faint">Geen voorstellen op dit project.</p>
            @else
                <div class="space-y-2">
                    @foreach ($approvals as $approval)
                        <div class="rounded-xl border bg-surface-850 p-3 {{ $approval->isOpen() ? 'border-amber-500/30' : 'border-surface-700' }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <b class="min-w-0 truncate text-sm font-semibold text-ink">{{ $approval->action }}</b>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $approval->badgeClasses() }}">{{ $approval->statusLabel() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted">{{ $approval->summary }}</p>
                            <p class="mt-1 text-xs text-faint">
                                {{ $approval->agentMeta()['name'] }}, {{ $approval->created_at->diffForHumans() }}
                                @if ($approval->decider), besloten door {{ $approval->decider->name }} @endif
                            </p>

                            @if ($approval->isOpen())
                                <a href="{{ route('approvals.index') }}"
                                   class="btn pri sm mt-2 w-full">
                                    Beslissen
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
