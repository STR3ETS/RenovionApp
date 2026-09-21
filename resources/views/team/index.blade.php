<x-layouts.app title="Team">

    <x-page-header title="Team" subtitle="{{ $users->count() }} teamleden">
        <a href="{{ route('team.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500">+ Teamlid</a>
    </x-page-header>

    <div class="space-y-2">
        @foreach ($users as $user)
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-navy-100 text-sm font-bold text-navy-700">
                    {{ str($user->name)->substr(0, 1)->upper() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="truncate text-sm font-semibold text-navy-900">{{ $user->name }}</span>
                        @if ($user->is(auth()->user()))
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">jij</span>
                        @endif
                    </span>
                    <span class="block truncate text-xs text-gray-500">
                        {{ $user->email }}{{ $user->phone ? ' · '.$user->phone : '' }}
                        @if ($user->role === \App\Enums\UserRole::Vakman)
                            · {{ $user->projects_count }} {{ $user->projects_count === 1 ? 'project' : 'projecten' }}
                        @endif
                        · {{ $user->tasks_count }} open {{ $user->tasks_count === 1 ? 'taak' : 'taken' }}
                    </span>
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {{ $user->role->badgeClasses() }}">
                    {{ $user->role->label() }}
                </span>
                <a href="{{ route('team.edit', $user) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Bewerken</a>
            </div>
        @endforeach
    </div>

</x-layouts.app>
