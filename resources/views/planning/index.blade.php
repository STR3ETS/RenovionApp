<x-layouts.app title="Planning">

    <div x-data="{ modal: false, formUser: '', formDate: '' }">

        <x-page-header title="Planning" subtitle="Week {{ $start->isoWeek }} · {{ $start->translatedFormat('j M') }} – {{ $start->copy()->addDays(4)->translatedFormat('j M Y') }}">
            <a href="{{ route('planning.index', ['week' => $start->copy()->subWeek()->toDateString()]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">←</a>
            <a href="{{ route('planning.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Vandaag</a>
            <a href="{{ route('planning.index', ['week' => $start->copy()->addWeek()->toDateString()]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">→</a>
            @can('manage-crm')
                <button @click="modal = true; formUser = ''; formDate = '{{ $start->toDateString() }}'" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500">+ Inplannen</button>
            @endcan
        </x-page-header>

        {{-- Weekgrid: capaciteit per vakman per dag --}}
        <div class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <table class="w-full min-w-[760px] border-separate border-spacing-1">
                <thead>
                    <tr>
                        <th class="w-32 p-2 text-left text-xs font-bold text-gray-500 uppercase">Vakman</th>
                        @foreach ($days as $day)
                            <th class="p-2 text-left text-xs font-bold uppercase {{ $day->isToday() ? 'text-brand-600' : 'text-gray-500' }}">
                                {{ $day->translatedFormat('D j M') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $rowUser)
                        <tr>
                            <td class="rounded-lg bg-white p-2 align-top">
                                <p class="text-sm font-semibold text-navy-900">{{ $rowUser->name }}</p>
                                <p class="text-xs text-gray-400">{{ $rowUser->role->label() }}</p>
                            </td>
                            @foreach ($days as $day)
                                @php $cellEntries = $entries->get($rowUser->id.'|'.$day->toDateString(), collect()); @endphp
                                <td class="group rounded-lg bg-white p-1.5 align-top {{ $day->isToday() ? 'ring-1 ring-brand-300' : '' }}">
                                    <div class="min-h-14 space-y-1">
                                        @foreach ($cellEntries as $entry)
                                            <div class="relative rounded-md border px-2 py-1 text-xs font-semibold {{ $entry->type->blockClasses() }}">
                                                @if ($entry->project)
                                                    <a href="{{ route('projects.show', $entry->project) }}" class="block truncate">{{ $entry->displayTitle() }}</a>
                                                @else
                                                    <span class="block truncate">{{ $entry->displayTitle() }}</span>
                                                @endif
                                                @if ($entry->start_time)
                                                    <span class="block text-[10px] font-normal opacity-70">{{ substr($entry->start_time, 0, 5) }}@if ($entry->end_time)–{{ substr($entry->end_time, 0, 5) }}@endif</span>
                                                @endif
                                                @can('manage-crm')
                                                    <form method="POST" action="{{ route('planning.destroy', $entry) }}" class="absolute top-0.5 right-1 hidden group-hover:block" onsubmit="return confirm('Planningsregel verwijderen?');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="opacity-50 hover:opacity-100" title="Verwijderen"><x-icon name="x-mark" class="h-3 w-3" /></button>
                                                    </form>
                                                @endcan
                                            </div>
                                        @endforeach
                                        @can('manage-crm')
                                            <button @click="modal = true; formUser = '{{ $rowUser->id }}'; formDate = '{{ $day->toDateString() }}'"
                                                    class="hidden w-full rounded-md border border-dashed border-gray-300 py-0.5 text-[10px] text-gray-400 transition group-hover:block hover:border-brand-400 hover:text-brand-600">+</button>
                                        @endcan
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state title="Nog geen vakmensen" subtitle="Maak gebruikers aan met rol Vakman om de capaciteitsplanning te vullen." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Inplannen-modal --}}
        @can('manage-crm')
        <div x-show="modal" x-cloak @click.self="modal = false" class="fixed inset-0 z-50 flex items-end justify-center bg-navy-900/60 backdrop-blur-sm lg:items-center">
            <form method="POST" action="{{ route('planning.store') }}" class="w-full max-w-md space-y-3 rounded-t-2xl bg-white p-6 lg:rounded-2xl">
                @csrf
                <h2 class="text-base font-bold text-navy-900">Inplannen</h2>

                <x-field label="Wie" name="user_id">
                    <select name="user_id" id="user_id" x-model="formUser" required class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Kies —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Datum" name="date">
                        <input type="date" name="date" id="date" x-model="formDate" required class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Type" name="type">
                        <select name="type" id="type" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach (\App\Enums\ScheduleEntryType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <x-field label="Project (optioneel)" name="project_id">
                    <select name="project_id" id="project_id" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Geen project —</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Titel (optioneel)" name="title">
                    <input type="text" name="title" id="title" placeholder="Bijv. terugbelafspraak De Vries" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                </x-field>

                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Van" name="start_time">
                        <input type="time" name="start_time" id="start_time" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Tot" name="end_time">
                        <input type="time" name="end_time" id="end_time" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 rounded-xl bg-brand-600 py-2.5 text-sm font-bold text-white transition hover:bg-brand-500">Inplannen</button>
                    <button type="button" @click="modal = false" class="rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-600">Annuleren</button>
                </div>
            </form>
        </div>
        @endcan
    </div>

</x-layouts.app>
