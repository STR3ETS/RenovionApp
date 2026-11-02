<x-layouts.app :title="$project->name">

    {{-- De kop: foto, kruimelpad, acties, titel en de metadatastrip --}}
    <x-project-band :project="$project" :next-step="$nextStep" :tab="$tab" />

    {{-- De negen fasen als doorlopende lijn --}}
    <section class="mb-4 rounded-2xl border border-surface-700 bg-surface-800 px-5 py-4">
        <x-phase-stepper :project="$project" />
    </section>

    {{-- De tabbalk: één pil per onderdeel van het dossier --}}
    <nav class="scrollbar-none mb-4 flex gap-1 overflow-x-auto rounded-full border border-surface-700 bg-surface-850 p-1">
        @foreach ($tabs as $item)
            <a href="{{ route('projects.show', [$project, 'tab' => $item['key']]) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition {{ $item['active'] ? 'bg-brand-500 text-white' : 'text-slate-400 hover:text-white' }}"
               @if ($item['active']) aria-current="page" @endif>
                {{ $item['label'] }}
                @if ($item['count'] > 0)
                    <span class="rounded-full px-1.5 text-[10px] font-bold {{ $item['active'] ? 'bg-white/25 text-white' : 'bg-surface-700 text-slate-400' }}">{{ $item['count'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    @include('projects.tabs.'.$tab)

</x-layouts.app>
