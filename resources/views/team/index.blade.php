<x-layouts.app title="Team">

    <x-page-header eyebrow="Teambeheer" title="Team" subtitle="{{ $users->count() }} teamleden">
        <a href="{{ route('team.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500">+ Teamlid</a>
    </x-page-header>

    <div class="space-y-2">
        @foreach ($users as $user)
            <div class="flex items-center gap-3 rounded-xl border border-surface-700 bg-surface-800 p-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-bold text-slate-200">
                    {{ str($user->name)->substr(0, 1)->upper() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                        @if ($user->is(auth()->user()))
                            <span class="rounded-full bg-surface-750 px-2 py-0.5 text-[10px] font-semibold text-slate-400">jij</span>
                        @endif
                    </span>
                    <span class="block truncate text-xs text-slate-400">
                        {{ $user->email }}{{ $user->phone ? ', '.$user->phone : '' }}
                        @if ($user->role === \App\Enums\UserRole::Uitvoerder)
                           , {{ $user->projects_count }} {{ $user->projects_count === 1 ? 'project' : 'projecten' }}
                        @endif
                       , {{ $user->tasks_count }} open {{ $user->tasks_count === 1 ? 'taak' : 'taken' }}
                    </span>
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {{ $user->role->badgeClasses() }}">
                    {{ $user->role->label() }}
                </span>
                <a href="{{ route('team.edit', $user) }}" class="rounded-lg border border-surface-600 bg-surface-800 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-surface-850">Bewerken</a>
            </div>
        @endforeach
    </div>

</x-layouts.app>
