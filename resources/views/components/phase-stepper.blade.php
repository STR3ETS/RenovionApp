@props(['project'])

@php
    use App\Enums\PhaseStatus;

    $phases = $project->phases;
    $current = $project->currentPhase();
@endphp

{{-- De fasen als één doorlopende lijn, zoals in het prototype: de lijn is oranje
     tot waar je bent, daarna grijs. Afgerond krijgt een vinkje, de huidige fase
     een oranje ring. --}}
@if ($phases->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'scrollbar-none overflow-x-auto']) }}>
        <ol class="flex min-w-max items-start px-1 py-1">
            @foreach ($phases as $phase)
                @php
                    $isGereed = $phase->status === PhaseStatus::Gereed;
                    $isActief = $current !== null && $phase->is($current);
                    $isGeblokkeerd = $phase->status === PhaseStatus::Geblokkeerd;
                    // De verbindingslijn hoort bij de stap waar hij naartoe loopt.
                    $lijnAan = $isGereed || $isActief;
                @endphp

                <li class="relative flex min-w-24 flex-1 flex-col items-center gap-1.5 px-1">
                    @unless ($loop->first)
                        <span class="absolute top-3.5 right-1/2 left-0 h-0.5 {{ $lijnAan ? 'bg-brand-500' : 'bg-surface-700' }}"></span>
                    @endunless
                    @unless ($loop->last)
                        <span class="absolute top-3.5 right-0 left-1/2 h-0.5 {{ $isGereed ? 'bg-brand-500' : 'bg-surface-700' }}"></span>
                    @endunless

                    <span class="relative z-10 flex h-7 w-7 items-center justify-center rounded-full border-2 text-[11px] font-bold transition
                        {{ $isGereed ? 'border-brand-500 bg-brand-500 text-white' : '' }}
                        {{ $isActief && ! $isGereed ? ($isGeblokkeerd ? 'border-red-500 bg-surface-800 text-red-400 ring-4 ring-red-500/20' : 'border-brand-500 bg-surface-800 text-brand-400 ring-4 ring-brand-500/20') : '' }}
                        {{ ! $isGereed && ! $isActief ? 'border-surface-600 bg-surface-800 text-slate-500' : '' }}"
                          title="{{ $phase->name }}, {{ $phase->status->label() }}">
                        @if ($isGereed)
                            <x-icon name="check" class="h-3.5 w-3.5" />
                        @else
                            {{ $phase->position }}
                        @endif
                    </span>

                    <span class="text-center text-[11px] leading-tight font-semibold
                        {{ $isActief ? 'text-brand-400' : ($isGereed ? 'text-slate-200' : 'text-slate-500') }}">
                        {{ $phase->name }}
                    </span>

                    @if ($isActief && ! in_array($phase->status, [PhaseStatus::Bezig, PhaseStatus::Gereed], true))
                        <span class="rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $phase->status->badgeClasses() }}">{{ $phase->status->label() }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif
