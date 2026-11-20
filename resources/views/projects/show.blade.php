<x-layouts.app :title="$project->name">

    {{-- De kop: foto, kruimelpad, acties, titel en de metadatastrip --}}
    <x-project-band :project="$project" :next-step="$nextStep" :tab="$tab" />

    {{-- Zonder opdracht geen uitvoering: de werkweergave blijft dicht tot de offerte getekend is --}}
    @unless ($project->hasOrder())
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-red-500/30 bg-red-500/10 p-4">
            <x-icon name="exclamation-triangle" class="h-6 w-6 shrink-0 text-red-300" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-white">Nog geen opdracht</p>
                <p class="mt-0.5 text-sm text-slate-200">
                    De offerte is niet getekend, dus de uitvoering staat geblokkeerd in de werkweergave.
                    Is de opdracht buiten het systeem om gegeven, leg dat dan vast.
                </p>
            </div>
            @can('manage-crm')
                <form method="POST" action="{{ route('projects.confirm-order', $project) }}"
                      onsubmit="return confirm('Vastleggen dat de opdracht is gegeven? Daarna kan de uitvoering beginnen.')">
                    @csrf
                    <button type="submit" class="rounded-full border border-surface-600 bg-surface-800 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:text-white">Opdracht bevestigen</button>
                </form>
            @endcan
        </div>
    @endunless

    {{-- De negen fasen als doorlopende lijn --}}
    <section class="mb-4 rounded-2xl border border-surface-700 bg-surface-800 px-5 py-4">
        <x-phase-stepper :project="$project" />
    </section>

    {{-- De tabbalk: één pil per onderdeel van het dossier --}}
    <x-tabs>
        @foreach ($tabs as $item)
            <x-tab :href="route('projects.show', [$project, 'tab' => $item['key']])"
                   :active="$item['active']" :count="$item['count'] > 0 ? $item['count'] : null">
                {{ $item['label'] }}
            </x-tab>
        @endforeach
    </x-tabs>

    @include('projects.tabs.'.$tab)

</x-layouts.app>
