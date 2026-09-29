<x-layouts.app :title="'Opleverrapport '.$report->project->name">

    <div class="print:hidden">
        <x-page-header :title="'Opleverrapport'" :subtitle="$report->project->name.' · '.$report->project->customer->name">
            @if ($report->isFullySigned())
                <span class="flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-800"><x-icon name="check" class="h-3.5 w-3.5" /> Volledig ondertekend</span>
            @endif
            <a href="{{ route('projects.show', $report->project) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Naar project</a>
        </x-page-header>

        <div class="mb-4 flex flex-wrap gap-2">
            @can('manage-crm')
                @unless ($report->isSignedByClient())
                    <form method="POST" action="{{ route('delivery-reports.store', $report->project) }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Hergenereren met actuele data</button>
                    </form>
                @endunless
                @if ($report->company_signed_at === null)
                    <form method="POST" action="{{ route('delivery-reports.sign', $report) }}" onsubmit="return confirm('Rapport ondertekenen namens Renovion?');">
                        @csrf
                        <button type="submit" class="rounded-xl bg-navy-950 px-4 py-2 text-sm font-bold text-white transition hover:bg-navy-900">Ondertekenen namens Renovion</button>
                    </form>
                @endif
            @endcan
            <button type="button" onclick="window.print()" class="flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <x-icon name="arrow-down-tray" class="h-4 w-4" /> Download als PDF
            </button>
        </div>

        @unless ($report->isSignedByClient())
            <p class="mb-4 text-xs text-gray-400">De klant vindt dit rapport onder "Actie van u nodig" in het klantportaal en ondertekent daar digitaal.</p>
        @endunless
    </div>

    <div class="mx-auto max-w-3xl rounded-2xl border border-gray-200 bg-white p-6 lg:p-10 print:border-0 print:p-0">
        @include('delivery-reports.partials.document', ['report' => $report, 'photos' => $photos])
    </div>

</x-layouts.app>
