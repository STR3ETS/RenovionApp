<x-layouts.portal :title="'Opleverrapport '.$report->project->name">

    <a href="{{ route('portal.show', $report->project) }}" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-brand-600 print:hidden">
        <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" /> Terug naar uw project
    </a>

    <div class="rounded-2xl bg-white p-6 shadow-sm lg:p-10 print:shadow-none">
        @include('delivery-reports.partials.document', ['report' => $report, 'photos' => $photos])
    </div>

    @unless ($report->isSignedByClient())
        <div class="mt-4 rounded-2xl border border-brand-200 bg-brand-50/60 p-5 print:hidden">
            <h2 class="text-lg font-bold text-navy-950">Akkoord met de oplevering?</h2>
            <p class="mt-1 mb-4 text-sm text-gray-500">Met uw ondertekening bevestigt u dat u het rapport heeft gezien en akkoord bent met de oplevering{{ count($report->snapshot['leftovers']) ? ', met inachtneming van de genoemde restpunten' : '' }}.</p>
            <form method="POST" action="{{ route('portal.report.sign', $report) }}" class="space-y-3 rounded-2xl border border-brand-100 bg-white p-5">
                @csrf
                <x-field label="Uw volledige naam" name="signed_name">
                    <input type="text" name="signed_name" id="signed_name" required value="{{ old('signed_name', auth()->user()->name) }}"
                           class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <label class="flex items-start gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="agree" value="1" required class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    Ik heb het opleverrapport gelezen en ga akkoord met de oplevering.
                </label>
                @error('agree')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="w-full rounded-xl bg-brand-500 py-3.5 text-base font-bold text-white transition hover:bg-brand-600 sm:w-auto sm:px-10">Rapport ondertekenen</button>
                <p class="text-xs text-gray-400">Uw naam, datum, tijd en IP-adres worden vastgelegd als digitale ondertekening.</p>
            </form>
        </div>
    @endunless

    <div class="mt-4 flex justify-center print:hidden">
        <button type="button" onclick="window.print()" class="flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
            <x-icon name="arrow-down-tray" class="h-4 w-4" /> Download als PDF
        </button>
    </div>

</x-layouts.portal>
