@props(['project'])

@php
    use App\Enums\PhaseStatus;

    $phases = $project->phases;
    $current = $project->currentPhase();
    $next = $current ? $phases->first(fn ($phase) => $phase->position > $current->position) : null;

    // Kleur van de actieve fase: oranje = bezig, amber = wachten, rood = geblokkeerd.
    $activeColor = fn ($phase) => match ($phase->status) {
        PhaseStatus::Geblokkeerd => 'bg-red-500',
        PhaseStatus::WachtOpKlant, PhaseStatus::WachtOpLeverancier => 'bg-amber-500',
        PhaseStatus::Gereed => 'bg-green-500',
        default => 'bg-brand-500',
    };
@endphp

@if ($phases->isNotEmpty())
    <div {{ $attributes->class('@container') }}>

        {{-- Smal: segmentbalk + huidige fase in woorden, alle fasen uitklapbaar (geen zijwaarts scrollen) --}}
        <div class="@[40rem]:hidden" x-data="{ open: false }">
            <div class="flex gap-1" aria-hidden="true">
                @foreach ($phases as $phase)
                    @php $isActief = $current !== null && $phase->is($current); @endphp
                    <span class="h-2 flex-1 rounded-full {{ $phase->status === PhaseStatus::Gereed ? 'bg-green-500' : ($isActief ? $activeColor($phase) : 'bg-gray-200') }}"></span>
                @endforeach
            </div>

            @if ($current)
                <div class="mt-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold tracking-widest text-gray-600 uppercase">Stap {{ $phases->search(fn ($phase) => $phase->is($current)) + 1 }} van {{ $phases->count() }}</p>
                        <p class="text-base font-bold text-navy-950">{{ $current->name }}</p>
                        @if ($next && $current->status !== PhaseStatus::Gereed)
                            <p class="mt-0.5 text-xs text-gray-500">Volgende: {{ $next->name }}</p>
                        @endif
                    </div>
                    <span class="mt-4 shrink-0 rounded-full px-2 py-0.5 text-xs font-bold {{ $current->status->badgeClasses() }}">{{ $current->status->label() }}</span>
                </div>
            @endif

            <button type="button" @click="open = !open" :aria-expanded="open"
                    class="tap mt-3 flex items-center gap-1 text-sm font-semibold text-brand-700">
                <span x-text="open ? 'Fasen verbergen' : 'Alle fasen bekijken'">Alle fasen bekijken</span>
                <x-icon name="chevron-down" class="h-4 w-4 transition" ::class="open && 'rotate-180'" />
            </button>

            <ol x-show="open" x-cloak x-transition.opacity class="mt-3">
                @foreach ($phases as $phase)
                    @php
                        $isGereed = $phase->status === PhaseStatus::Gereed;
                        $isActief = $current !== null && $phase->is($current) && ! $isGereed;
                    @endphp
                    <li class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold
                                {{ $isGereed ? 'bg-green-500 text-white' : ($isActief ? $activeColor($phase).' text-white' : 'bg-gray-200 text-gray-500') }}">
                                @if ($isGereed)
                                    <x-icon name="check" class="h-3 w-3" />
                                @else
                                    {{ $phase->position }}
                                @endif
                            </span>
                            @unless ($loop->last)
                                <span class="w-0.5 flex-1 {{ $isGereed ? 'bg-green-300' : 'bg-gray-200' }}"></span>
                            @endunless
                        </div>
                        <div class="min-w-0 flex-1 pb-3">
                            <p class="text-sm {{ $isActief ? 'font-bold text-navy-950' : ($isGereed ? 'font-medium text-gray-600' : 'text-gray-500') }}">{{ $phase->name }}</p>
                            <p class="text-xs {{ $isActief ? 'text-gray-600' : 'text-gray-500' }}">
                                @if ($isGereed && $phase->completed_at)
                                    Gereed op {{ $phase->completed_at->translatedFormat('j M') }}
                                @else
                                    {{ $phase->status->label() }}
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- Breed: horizontale stepper, links uitgelijnd met stappen van maximaal 80px --}}
        <div class="hidden @[40rem]:block">
            <ol class="flex items-start">
                @foreach ($phases as $phase)
                    @php
                        $isGereed = $phase->status === PhaseStatus::Gereed;
                        $isActief = $current !== null && $phase->is($current);
                    @endphp
                    <li class="relative flex max-w-20 min-w-0 flex-1 flex-col items-center gap-1.5 px-0.5 text-center" title="{{ $phase->name }} - {{ $phase->status->label() }}">
                        @unless ($loop->last)
                            <span class="absolute top-3.75 right-[calc(-50%+1.375rem)] left-[calc(50%+1.375rem)] h-0.5 rounded-full {{ $isGereed ? 'bg-green-400' : 'bg-gray-200' }}"></span>
                        @endunless
                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition
                            {{ $isGereed ? 'bg-green-500 text-white' : '' }}
                            {{ $isActief && ! $isGereed ? ($phase->status === PhaseStatus::Geblokkeerd ? 'bg-red-500 text-white' : 'bg-brand-500 text-white ring-4 ring-brand-100') : '' }}
                            {{ ! $isGereed && ! $isActief ? 'bg-gray-200 text-gray-500' : '' }}">
                            @if ($isGereed)
                                <x-icon name="check" class="h-4 w-4" />
                            @else
                                {{ $phase->position }}
                            @endif
                        </span>
                        {{-- Zachte afbreking: het enige woord dat breder is dan een smalle stap --}}
                        <span class="text-[10px] leading-tight font-semibold {{ $isActief ? 'text-navy-900' : ($isGereed ? 'text-gray-500' : 'text-gray-500') }}">
                            {{ str_replace('Werkvoorbereiding', "Werk\u{AD}voorbereiding", $phase->name) }}
                        </span>
                        @if ($isActief && ! in_array($phase->status, [PhaseStatus::Bezig, PhaseStatus::Gereed], true))
                            <span class="rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $phase->status->badgeClasses() }}">{{ $phase->status->label() }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
@endif
