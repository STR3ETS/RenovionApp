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

    {{-- De rechtenmatrix: wie ziet welk scherm. Een collega die iets niet ziet,
         kijkt hier of dat zijn rol is in plaats van een fout. --}}
    <section x-data="{ open: false }" class="mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-surface-700 bg-surface-800 px-5 py-4">
            <div>
                <h2 class="text-sm font-bold text-white">Rechten<span class="text-brand-500">matrix</span></h2>
                <p class="mt-0.5 text-xs text-slate-400">
                    {{ count($roles) }} rollen tegenover {{ collect($sections)->sum(fn ($s) => count($s['items'])) }} schermen.
                </p>
            </div>
            <button @click="open = !open"
                    class="rounded-full border border-surface-600 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:text-white">
                <span x-text="open ? 'Verberg matrix' : 'Toon matrix'"></span>
            </button>
        </div>

        <div x-show="open" x-cloak class="mt-3 overflow-x-auto rounded-2xl border border-surface-700 bg-surface-800">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-surface-700 bg-surface-850">
                        <th class="sticky left-0 z-10 bg-surface-850 px-4 py-3 text-left text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                            Scherm
                        </th>
                        @foreach ($roles as $role)
                            <th class="px-2 py-3 text-center text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                <span class="block max-w-16 truncate" title="{{ $role->label() }}">{{ $role->label() }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $id => $section)
                        <tr class="border-b border-surface-700 bg-surface-850/60">
                            <td colspan="{{ count($roles) + 1 }}" class="px-4 py-2 text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                                {{ $section['group'] }}, {{ $section['label'] }}
                            </td>
                        </tr>
                        @foreach ($section['items'] as $module => [$label, $route])
                            <tr class="border-b border-surface-700/60 last:border-b-0">
                                <td class="sticky left-0 z-10 bg-surface-800 px-4 py-2">
                                    <span class="block truncate text-slate-200">{{ $label }}</span>
                                    @if (in_array($module, $managementOnly, true))
                                        <span class="text-[10px] text-slate-500">alleen directie</span>
                                    @endif
                                </td>
                                @foreach ($roles as $role)
                                    <td class="px-2 py-2 text-center">
                                        @if ($matrix[$id][$module][$role->value])
                                            <x-icon name="check" class="mx-auto h-3.5 w-3.5 text-green-400" />
                                        @else
                                            <span class="mx-auto block h-1 w-1 rounded-full bg-surface-600"></span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

</x-layouts.app>
