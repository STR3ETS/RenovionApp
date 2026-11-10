<x-layouts.app title="Kwaliteit en oplevering">
    <x-page-header title="Kwaliteit en oplevering" eyebrow="Projecten" category="project"
                   subtitle="Acht stappen van werk afgerond tot nazorg. Elke stap heeft een harde voorwaarde." />

    @if ($openSnagsTotal > 0)
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
            <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-300" />
            <p class="text-sm text-slate-200">
                <b class="text-white">{{ $openSnagsTotal }} {{ $openSnagsTotal === 1 ? 'restpunt staat' : 'restpunten staan' }} open.</b>
                Zolang die er zijn, kun je niet naar de klantinspectie.
            </p>
        </div>
    @endif

    @if ($rows->isEmpty())
        <x-empty-state icon="shield-check" category="project" title="Geen lopende projecten"
                       subtitle="Zodra er een project loopt, staat de opleverketen hier klaar." />
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php
                    $project = $row['project'];
                    $review = $row['review'];
                    $step = $review?->step ?? 0;
                @endphp

                <a href="{{ route('quality.show', $project) }}"
                   class="motion-lift block overflow-hidden rounded-2xl border border-surface-700 bg-surface-800 transition hover:border-surface-600">
                    <div class="p-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-project-500/15 text-project-400">
                                <x-icon name="shield-check" class="h-5 w-5" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <b class="block truncate text-sm font-bold text-white">{{ $project->name }}</b>
                                <small class="block truncate text-xs text-slate-400">
                                    {{ $project->customer?->name }},
                                    voortgang {{ $project->progress }}%,
                                    stap {{ $step + 1 }} van {{ count($flow) }}: {{ $flow[$step][0] }}
                                </small>
                            </span>

                            @if ($row['openSnags']->isNotEmpty())
                                <span class="shrink-0 rounded-full bg-amber-500/15 px-2.5 py-1 text-[11px] font-bold text-amber-300">
                                    {{ $row['openSnags']->count() }} {{ $row['openSnags']->count() === 1 ? 'restpunt' : 'restpunten' }}
                                </span>
                            @endif

                            @if ($review?->wkb)
                                <span class="shrink-0 rounded-full bg-calc-500/15 px-2.5 py-1 text-[11px] font-bold text-calc-300" title="Valt onder de Wet kwaliteitsborging">
                                    Wkb
                                </span>
                            @endif
                        </div>

                        {{-- De acht stappen --}}
                        <div class="mt-3 flex gap-1">
                            @foreach ($flow as $index => [$label, $condition])
                                <span class="h-1 flex-1 rounded-full {{ $index <= $step ? 'bg-project-500' : 'bg-surface-700' }}"
                                      title="{{ $label }}: {{ $condition }}"></span>
                            @endforeach
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
