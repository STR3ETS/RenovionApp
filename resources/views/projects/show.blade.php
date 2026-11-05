<x-layouts.app :title="$project->name">

    {{-- De kop: foto, kruimelpad, acties, titel en de metadatastrip --}}
    <x-project-band :project="$project" :next-step="$nextStep" :tab="$tab" />

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
