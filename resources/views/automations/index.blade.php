<x-layouts.app title="Automations">

    <x-page-header title="Automations" subtitle="Trigger → voorwaarden → actie. Draait automatisch elke 15 minuten." />

    <div class="space-y-2">
        @foreach ($automations as $automation)
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-100 text-navy-700">
                    <x-icon name="cog" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-navy-900">{{ $automation['name'] }}</span>
                    <span class="block text-xs text-gray-500">{{ $automation['description'] }}</span>
                </span>
                <span class="text-right text-xs whitespace-nowrap text-gray-400">
                    {{ $automation['runs'] }}× uitgevoerd
                    @if ($automation['last_run_at'])
                        <span class="block">laatst {{ $automation['last_run_at']->translatedFormat('j M H:i') }}</span>
                    @endif
                </span>
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
        Automations voor vakmanstatus, reviewflow en WhatsApp volgen in fase 3.
    </p>

</x-layouts.app>
