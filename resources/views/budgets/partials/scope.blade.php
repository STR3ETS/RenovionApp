@php
    $scope = $budget->scopeLists();
    $blokken = [
        'docs' => ['Op basis van', 'De tekeningen en stukken waarop deze begroting rust'],
        'incl' => ['Inbegrepen', 'Wat er zeker in zit'],
        'excl' => ['Niet inbegrepen', 'Wat de klant hier niet voor krijgt'],
    ];
@endphp

{{-- Deze drie lijsten gaan ongewijzigd mee naar de offerte, zodat de klant
     precies dezelfde uitsluitingen leest als de calculator bedoelde. --}}
<div x-data="{ bewerken: false }" class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
    <div class="mb-4 flex items-center justify-between gap-2">
        <h2 class="text-sm font-bold text-white">Uitgangs<span class="text-brand-500">punten</span></h2>
        @if ($budget->isDraft())
            <button @click="bewerken = !bewerken" class="text-xs font-semibold text-slate-400 transition hover:text-white">
                <span x-text="bewerken ? 'Sluiten' : 'Bewerken'"></span>
            </button>
        @endif
    </div>

    <div x-show="!bewerken" class="space-y-4">
        @foreach ($blokken as $key => [$label, $hint])
            <div>
                <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">{{ $label }}</p>
                @if ($scope[$key] === [])
                    <p class="mt-1 text-xs text-slate-600">Nog niets vastgelegd.</p>
                @else
                    <ul class="mt-1.5 space-y-1">
                        @foreach ($scope[$key] as $punt)
                            <li class="flex items-start gap-2 text-sm text-slate-300">
                                <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full {{ $key === 'excl' ? 'bg-red-400' : 'bg-green-400' }}"></span>
                                {{ $punt }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>

    @if ($budget->isDraft())
        <form x-show="bewerken" x-cloak method="POST" action="{{ route('budgets.update', $budget) }}" class="space-y-3">
            @csrf
            @method('PATCH')

            @foreach ($blokken as $key => [$label, $hint])
                <x-field :label="$label" :name="'scope_'.$key">
                    <textarea name="scope_{{ $key }}" id="scope_{{ $key }}" rows="3" placeholder="{{ $hint }}, een regel per punt"
                              class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">{{ implode("\n", $scope[$key]) }}</textarea>
                </x-field>
            @endforeach

            <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Opslaan</button>
        </form>
    @endif
</div>
