<x-layouts.app title="Automations">

    <x-page-header title="Automations" subtitle="Nova-rules (briefing §12): per regel instelbaar — alleen signaleren, eerst bevestigen of automatisch uitvoeren. Draait elke 15 minuten." />

    <div class="space-y-2">
        @foreach ($automations as $automation)
            <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-white p-4 {{ $automation['mode'] === \App\Enums\AutomationMode::Uit ? 'border-gray-100 opacity-60' : 'border-gray-200' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $automation['mode'] === \App\Enums\AutomationMode::Automatisch ? 'bg-green-100 text-green-700' : 'bg-navy-100 text-navy-700' }}">
                    <x-icon name="bolt" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-navy-900">{{ $automation['name'] }}</span>
                    <span class="block text-xs text-gray-500">{{ $automation['description'] }}</span>
                    <span class="mt-0.5 block text-[11px] text-gray-400">{{ $automation['mode']->description() }}</span>
                </span>
                <span class="text-right text-xs whitespace-nowrap text-gray-400">
                    {{ $automation['runs'] }}× uitgevoerd
                    @if ($automation['last_run_at'])
                        <span class="block">laatst {{ $automation['last_run_at']->translatedFormat('j M H:i') }}</span>
                    @endif
                </span>
                @can('manage-team')
                    <form method="POST" action="{{ route('automations.update', $automation['key']) }}">
                        @csrf @method('PATCH')
                        <select name="mode" onchange="this.form.submit()" class="rounded-lg border-gray-200 py-1.5 text-xs font-semibold focus:border-brand-500 focus:ring-brand-500">
                            @foreach (\App\Enums\AutomationMode::cases() as $mode)
                                <option value="{{ $mode->value }}" @selected($automation['mode'] === $mode)>{{ $mode->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $automation['mode']->badgeClasses() }}">{{ $automation['mode']->label() }}</span>
                @endcan
            </div>
        @endforeach

        {{-- In code ingebouwde automations --}}
        <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 opacity-80">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700">
                <x-icon name="check" class="h-4.5 w-4.5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-navy-900">Offerte akkoord → project aanmaken</span>
                <span class="block text-xs text-gray-500">Ingebouwd in de offerteflow: bij akkoord wordt automatisch een project aangemaakt en springt de lead naar "Project".</span>
            </span>
            <span class="text-xs whitespace-nowrap text-gray-400">altijd actief</span>
        </div>
    </div>

    <p class="mt-4 text-xs text-gray-400">
        Bij "signaleren" en "eerst bevestigen" meldt Nova zich in het teamkanaal Algemeen. Automations voor WhatsApp en reviewflow volgen later.
    </p>

</x-layouts.app>
