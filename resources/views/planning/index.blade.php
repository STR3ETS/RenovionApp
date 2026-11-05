<x-layouts.app title="Planning">

    @php
        // Mobiel opent op vandaag als die in deze week valt, anders op maandag.
        $todayIndex = $days->search(fn ($day) => $day->isToday());
        $isCurrentWeek = $todayIndex !== false;
        $todayIndex = $isCurrentWeek ? $todayIndex : 0;
    @endphp

    <div x-data="{ modal: false, formUser: '', formDate: '', day: {{ $todayIndex }}, dates: @js($days->map->toDateString()), detail: null, details: @js($details) }">

        <x-page-header :eyebrow="auth()->user()->can('manage-crm') ? 'Capaciteit' : 'Mijn week'" category="planning" title="Planning" subtitle="Week {{ $start->isoWeek }}, {{ $start->translatedFormat('j M') }} t/m {{ $start->copy()->addDays(4)->translatedFormat('j M Y') }}">
            <a href="{{ route('planning.index', ['week' => $start->copy()->subWeek()->toDateString()]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 app:hidden">←</a>
            <a href="{{ route('planning.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 app:hidden">Vandaag</a>
            <a href="{{ route('planning.index', ['week' => $start->copy()->addWeek()->toDateString()]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 app:hidden">→</a>
            @can('manage-crm')
                <button @click="modal = true; formUser = ''; formDate = dates[day]" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">+ Inplannen</button>
            @endcan
        </x-page-header>

        {{-- ===== Mobiel ===== --}}
        <div class="lg:hidden">
            @nativeapp
                <div class="mb-3 flex items-center justify-between">
                    <a href="{{ route('planning.index', ['week' => $start->copy()->subWeek()->toDateString()]) }}" class="app-iconbtn tap" title="Vorige week"><x-icon name="chevron-right" class="h-4 w-4 rotate-180" /></a>
                    <a href="{{ route('planning.index') }}" class="font-[family-name:var(--font-display)] text-[15px] font-semibold text-navy-900">Week van {{ $start->translatedFormat('j M') }}</a>
                    <a href="{{ route('planning.index', ['week' => $start->copy()->addWeek()->toDateString()]) }}" class="app-iconbtn tap" title="Volgende week"><x-icon name="chevron-right" class="h-4 w-4" /></a>
                </div>
            @endnativeapp

            @can('manage-crm')
                {{-- Planner: dagbalk + per persoon wat er die dag staat, en wie vrij is --}}
                <div data-pill="over" class="mb-4 grid grid-cols-5 gap-1.5">
                    @foreach ($days as $i => $day)
                        @php $heeftPlanning = $rows->contains(fn ($rowUser) => $entries->has($rowUser->id.'|'.$day->toDateString())); @endphp
                        <button type="button" @click="day = {{ $i }}"
                                :class="day === {{ $i }} ? 'border-navy-950 bg-navy-950 text-white' : 'border-gray-200 bg-white {{ $day->isToday() ? 'text-brand-700' : 'text-navy-900' }}'"
                                class="flex flex-col items-center rounded-xl border py-2 transition app:rounded-[14px] app:font-[family-name:var(--font-display)]">
                            <span class="text-[10px] font-bold uppercase opacity-70">{{ $day->translatedFormat('D') }}</span>
                            <span class="text-base leading-tight font-bold app:text-lg">{{ $day->day }}</span>
                            <span class="mt-1 h-1.5 w-1.5 rounded-full {{ $heeftPlanning ? 'bg-planning-500' : 'bg-transparent' }}"></span>
                        </button>
                    @endforeach
                </div>

                @foreach ($days as $i => $day)
                    <section x-show="day === {{ $i }}" @if ($i !== $todayIndex) x-cloak @endif>
                        <h2 class="mb-2 text-sm font-bold text-navy-950">
                            {{ $day->isToday() ? 'Vandaag, ' : '' }}{{ $day->translatedFormat('l j F') }}
                        </h2>
                        <div class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                            @forelse ($rows as $rowUser)
                                @php $cellEntries = $entries->get($rowUser->id.'|'.$day->toDateString(), collect()); @endphp
                                <div class="flex gap-3 p-3 app:block app:px-4">
                                    <div class="w-20 shrink-0 pt-1 app:mb-2 app:flex app:w-auto app:items-center app:gap-3 app:pt-0">
                                        @nativeapp
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style="background: {{ ['#0D1A2E', '#F26A1B', '#3E6BA8', '#C2506D', '#1E8A5A', '#7C5CD6'][crc32($rowUser->name) % 6] }}">{{ str($rowUser->name)->explode(' ')->take(2)->map(fn ($deel) => str($deel)->substr(0, 1)->upper())->implode('') }}</span>
                                        @endnativeapp
                                        <p class="truncate text-sm font-semibold text-navy-900 app:min-w-0 app:flex-1">{{ str($rowUser->name)->before(' ') }}</p>
                                        <p class="truncate text-[11px] text-gray-500">{{ $rowUser->role->label() }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1 space-y-1.5">
                                        @forelse ($cellEntries as $entry)
                                            <button type="button" @click="detail = details[{{ $entry->id }}]"
                                                    class="block w-full rounded-lg border px-2.5 py-1.5 text-left app:rounded-[9px] app:border-0 app:border-l-[3px] {{ $entry->type->blockClasses() }}">
                                                <span class="block truncate text-sm font-semibold">{{ $entry->displayTitle() }}</span>
                                                <span class="block text-xs opacity-70">{{ $details[$entry->id]['time'] }}, {{ $entry->type->label() }}</span>
                                            </button>
                                        @empty
                                            <div class="flex items-center justify-between gap-2 py-1">
                                                <span class="flex items-center gap-1.5 text-sm font-medium text-green-700"><x-signal-dot color="green" /> Vrij</span>
                                                <button type="button" @click="modal = true; formUser = '{{ $rowUser->id }}'; formDate = '{{ $day->toDateString() }}'"
                                                        class="tap rounded-lg border border-dashed border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-500 transition active:border-brand-400 active:text-brand-600">
                                                    + Inplannen
                                                </button>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @empty
                                <div class="p-4">
                                    <x-empty-state title="Nog geen uitvoerders" subtitle="Maak gebruikers aan met rol Uitvoerder om de capaciteitsplanning te vullen." icon="calendar" category="planning" />
                                </div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            @else
                {{-- Uitvoerder: agenda van vandaag tot en met vrijdag (andere weken: hele week) --}}
                @foreach ($isCurrentWeek ? $days->slice($todayIndex) : $days as $day)
                    @php $cellEntries = $entries->get(auth()->id().'|'.$day->toDateString(), collect()); @endphp
                    <section class="{{ $cellEntries->isEmpty() ? 'mb-1 flex items-baseline gap-2 rounded-xl bg-white px-3 py-2.5' : 'mt-3 mb-5' }}">
                        <h2 class="{{ $cellEntries->isEmpty() ? 'contents' : 'mb-2 flex items-baseline gap-2' }}">
                            <span class="{{ $cellEntries->isEmpty() ? 'text-sm font-semibold' : 'text-base font-bold' }} {{ $day->isToday() ? 'text-brand-700' : 'text-navy-950' }}">
                                {{ $day->isToday() ? 'Vandaag' : ($day->isTomorrow() ? 'Morgen' : ucfirst($day->translatedFormat('l'))) }}
                            </span>
                            <span class="text-xs text-gray-500">{{ $day->translatedFormat('j F') }}</span>
                        </h2>
                        @forelse ($cellEntries as $entry)
                            <button type="button" @click="detail = details[{{ $entry->id }}]"
                                    class="mb-2 flex w-full items-center gap-3 rounded-2xl border border-gray-200 border-l-4 border-l-planning-500 bg-white p-3 text-left">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $entry->displayTitle() }}</span>
                                    <span class="block text-xs text-gray-500">{{ $details[$entry->id]['time'] }}, {{ $entry->type->label() }}</span>
                                    @if ($details[$entry->id]['address'])
                                        <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $details[$entry->id]['address'] }}</span>
                                    @endif
                                </span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-300" />
                            </button>
                        @empty
                            <span class="ml-auto text-xs text-gray-500">Niets gepland</span>
                        @endforelse
                    </section>
                @endforeach
            @endcan
        </div>

        {{-- Detailpaneel (mobiel): tikken op een planningsblok --}}
        <div data-popup x-show="detail" x-cloak @click.self="detail = null" @keydown.escape.window="detail = null"
             class="fixed inset-0 z-50 flex items-end bg-navy-950/60 backdrop-blur-sm lg:hidden">
            <template x-if="detail">
                <div x-swipe-dismiss="detail = null" class="w-full rounded-t-2xl bg-white px-4 pt-3 pb-[calc(1rem_+_env(safe-area-inset-bottom))]">
                    <x-sheet-handle class="mb-3" />
                    <span class="inline-block rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="detail.classes" x-text="detail.type"></span>
                    <h2 class="mt-2 text-lg font-bold text-navy-950" x-text="detail.title"></h2>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex gap-3" x-show="detail.user">
                            <dt class="w-16 shrink-0 text-gray-500">Wie</dt>
                            <dd class="font-medium text-navy-900" x-text="detail.user"></dd>
                        </div>
                        <div class="flex gap-3">
                            <dt class="w-16 shrink-0 text-gray-500">Wanneer</dt>
                            <dd class="font-medium text-navy-900"><span x-text="detail.date"></span>, <span x-text="detail.time.toLowerCase()"></span></dd>
                        </div>
                        <div class="flex gap-3" x-show="detail.address">
                            <dt class="w-16 shrink-0 text-gray-500">Adres</dt>
                            <dd class="font-medium text-navy-900" x-text="detail.address"></dd>
                        </div>
                        <div class="flex gap-3" x-show="detail.notes">
                            <dt class="w-16 shrink-0 text-gray-500">Notitie</dt>
                            <dd class="whitespace-pre-line text-navy-900" x-text="detail.notes"></dd>
                        </div>
                    </dl>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a :href="detail.project" x-show="detail.project" class="flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white">
                            <x-icon name="building-office" class="h-4 w-4" /> Project openen
                        </a>
                        <a :href="detail.maps" x-show="detail.maps" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 rounded-xl border border-gray-300 py-2.5 text-sm font-semibold text-navy-900">
                            <x-icon name="paper-airplane" class="h-4 w-4" /> Route
                        </a>
                    </div>
                    <form method="POST" :action="detail.delete" x-show="detail.delete" class="mt-2" onsubmit="return confirm('Planningsregel verwijderen?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full rounded-xl py-2.5 text-sm font-semibold text-red-600 transition active:bg-red-50">Verwijderen</button>
                    </form>
                </div>
            </template>
        </div>

        {{-- ===== Desktop: weekgrid, capaciteit per uitvoerder per dag ===== --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[760px] border-separate border-spacing-1">
                <thead>
                    <tr>
                        <th class="w-32 p-2 text-left text-xs font-bold text-gray-500 uppercase">Uitvoerder</th>
                        @foreach ($days as $day)
                            <th class="p-2 text-left text-xs font-bold uppercase {{ $day->isToday() ? 'text-brand-700' : 'text-gray-500' }}">
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
                                <p class="text-xs text-gray-500">{{ $rowUser->role->label() }}</p>
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
                                                    class="hidden w-full rounded-md border border-dashed border-gray-300 py-0.5 text-[10px] text-gray-500 transition group-hover:block hover:border-brand-400 hover:text-brand-600">+</button>
                                        @endcan
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state title="Nog geen uitvoerders" subtitle="Maak gebruikers aan met rol Uitvoerder om de capaciteitsplanning te vullen." icon="calendar" category="planning" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Inplannen-modal --}}
        @can('manage-crm')
        <div data-popup x-show="modal" x-cloak @click.self="modal = false" class="fixed inset-0 z-50 flex items-end justify-center bg-navy-900/60 backdrop-blur-sm lg:items-center">
            <form method="POST" action="{{ route('planning.store') }}" x-swipe-dismiss="modal = false" class="max-h-[90dvh] w-full max-w-md space-y-3 overflow-y-auto rounded-t-2xl bg-white p-6 lg:rounded-2xl">
                @csrf
                <x-sheet-handle class="-mt-3 lg:hidden" />
                <h2 class="text-base font-bold text-navy-900">Inplannen</h2>

                <x-field label="Wie" name="user_id">
                    <select name="user_id" id="user_id" x-model="formUser" required class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">- Kies -</option>
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
                        <option value="">- Geen project -</option>
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
                    <button type="submit" class="flex-1 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Inplannen</button>
                    <button type="button" @click="modal = false" class="rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-600">Annuleren</button>
                </div>
            </form>
        </div>
        @endcan
    </div>

</x-layouts.app>
