@props(['project'])

@php
    use App\Enums\PhaseStatus;

    $phases = $project->phases;
    $current = $project->currentPhase();
@endphp

@if ($phases->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'overflow-x-auto pb-1']) }}>
        <ol class="flex min-w-max items-start">
            @foreach ($phases as $phase)
                @php
                    $isGereed = $phase->status === PhaseStatus::Gereed;
                    $isActief = $current !== null && $phase->is($current);
                @endphp
                <li class="flex items-start">
                    <div class="flex w-20 flex-col items-center gap-1.5 text-center" title="{{ $phase->name }} — {{ $phase->status->label() }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold transition
                            {{ $isGereed ? 'bg-green-500 text-white' : '' }}
                            {{ $isActief && ! $isGereed ? ($phase->status === PhaseStatus::Geblokkeerd ? 'bg-red-500 text-white' : 'bg-brand-500 text-white ring-4 ring-brand-100') : '' }}
                            {{ ! $isGereed && ! $isActief ? 'bg-gray-200 text-gray-500' : '' }}">
                            @if ($isGereed)
                                <x-icon name="check" class="h-3.5 w-3.5" />
                            @else
                                {{ $phase->position }}
                            @endif
                        </span>
                        <span class="text-[10px] leading-tight font-semibold {{ $isActief ? 'text-navy-900' : ($isGereed ? 'text-gray-500' : 'text-gray-400') }}">
                            {{ $phase->name }}
                        </span>
                        @if ($isActief && ! in_array($phase->status, [PhaseStatus::Bezig, PhaseStatus::Gereed], true))
                            <span class="rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $phase->status->badgeClasses() }}">{{ $phase->status->label() }}</span>
                        @endif
                    </div>
                    @unless ($loop->last)
                        <span class="mt-3.5 h-0.5 w-4 shrink-0 rounded-full {{ $isGereed ? 'bg-green-400' : 'bg-gray-200' }}"></span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </div>
@endif
