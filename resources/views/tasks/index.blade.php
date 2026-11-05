<x-layouts.app title="Taken">

    <div x-data="{ nieuw: false }">
        <x-page-header :eyebrow="auth()->user()->can('manage-crm') ? 'Werk organiseren' : 'Mijn werk'" category="planning" title="Taken" subtitle="{{ \App\Support\Tally::of($tasks->total(), 'taak', 'taken') }} in deze weergave">
            <button @click="nieuw = !nieuw" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600"><x-app-label web="+ Taak" app="Taak" /></button>
        </x-page-header>

        {{-- Quick add --}}
        <form x-show="nieuw" x-cloak method="POST" action="{{ route('tasks.store') }}"
              class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-brand-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <input aria-label="Titel" type="text" name="title" required placeholder="Wat moet er gebeuren? *"
                   class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2">
            <input aria-label="Deadline" type="date" name="deadline" value="{{ today()->toDateString() }}"
                   class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            <select aria-label="Prioriteit" name="priority" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach (\App\Enums\TaskPriority::cases() as $priority)
                    <option value="{{ $priority->value }}" @selected($priority === \App\Enums\TaskPriority::Normaal)>{{ $priority->label() }}</option>
                @endforeach
            </select>
            @can('manage-crm')
                <select aria-label="Eigenaar" name="owner_id" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Eigenaar: ikzelf</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <select aria-label="Klant" name="customer_id" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">- Klant (optioneel) -</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            @endcan
            <select aria-label="Project" name="project_id" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">- Project (optioneel) -</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-navy-900 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 lg:col-span-2">Taak aanmaken</button>
        </form>

        {{-- Filters --}}
        <div class="mb-4 flex flex-wrap gap-1.5 app:-mx-4 app:flex-nowrap app:gap-1 app:overflow-x-auto app:px-4 app:pb-1">
            <a href="{{ route('tasks.index') }}"
               class="tap rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === null ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">Open</a>
            @foreach (\App\Enums\TaskStatus::cases() as $statusOption)
                <a href="{{ route('tasks.index', ['status' => $statusOption->value]) }}"
                   class="tap rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === $statusOption ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">{{ $statusOption->label() }}</a>
            @endforeach
        </div>

        {{-- Takenlijst --}}
        @nativeapp
            {{-- iOS/Android-app (prototype Renovion OS): één kaart, per taak de velden onder elkaar --}}
            @if ($tasks->isEmpty())
                <x-empty-state title="Geen taken in deze weergave" subtitle="Voeg een taak toe met de knop hierboven." icon="clipboard-check" category="planning" />
            @else
                <div class="app-card">
                    @foreach ($tasks as $task)
                        <div class="app-rec">
                            <div class="flex items-start gap-3">
                                @if ($task->status->isOpen())
                                    <button x-data @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                                            class="group -m-2.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full" title="Afronden" aria-label="Taak afronden">
                                        <span class="h-[22px] w-[22px] rounded-[7px] border-2 border-gray-300 group-active:border-green-500 group-active:bg-green-50"></span>
                                    </button>
                                @else
                                    <span class="-m-2.5 flex h-11 w-11 shrink-0 items-center justify-center"><span class="flex h-[22px] w-[22px] items-center justify-center rounded-[7px] bg-green-100 text-green-700"><x-icon name="check" class="h-3.5 w-3.5" /></span></span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="app-lbl">Taak</span>
                                    <h4 class="{{ $task->status->isOpen() ? '' : 'text-gray-500 line-through' }}">{{ $task->title }}</h4>
                                </span>
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $task->priority->dotClasses() }}" title="Prioriteit: {{ $task->priority->label() }}"></span>
                            </div>
                            <div class="app-fields">
                                <div>
                                    <span class="app-lbl">Project / klant</span>
                                    @if ($task->project)
                                        <a href="{{ route('projects.show', $task->project) }}" class="block font-medium text-navy-900">{{ $task->project->name }}</a>
                                    @endif
                                    @if ($task->customer)
                                        <small>{{ $task->customer->name }}</small>
                                    @elseif (! $task->project)
                                        <b>Algemeen</b>
                                    @endif
                                </div>
                                <div>
                                    <span class="app-lbl">Eigenaar</span>
                                    <b>{{ $task->owner?->name ?? 'Geen eigenaar' }}</b>
                                </div>
                                <div>
                                    <span class="app-lbl">Deadline</span>
                                    <b class="{{ $task->isOverdue() ? 'text-red-700' : '' }}">{{ $task->deadline ? ($task->isOverdue() ? 'Verlopen, ' : '').($task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M')) : 'Geen' }}</b>
                                </div>
                                <div>
                                    <span class="app-lbl">Status</span>
                                    @if ($task->status->isOpen())
                                        <select x-data aria-label="Status" @change="patchJson('{{ route('tasks.status', $task) }}', { status: $event.target.value }).then(() => window.location.reload())"
                                                class="!min-h-9 w-full py-1 pr-7 pl-3 !text-sm">
                                            @foreach (\App\Enums\TaskStatus::cases() as $statusOption)
                                                <option value="{{ $statusOption->value }}" @selected($statusOption === $task->status)>{{ $statusOption->label() }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <b>{{ $task->status->label() }}</b>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
        <div class="space-y-2">
            @forelse ($tasks as $task)
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 bg-white p-3 {{ $task->isOverdue() ? 'border-l-red-500' : 'border-l-planning-500' }}">
                    @if ($task->status->isOpen())
                        {{-- Tikvlak 44px rond het rondje van 24px: goed te raken op de telefoon. --}}
                        <button
                            x-data
                            @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                            class="group -m-2.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                            title="Afronden"><span class="h-6 w-6 rounded-full border-2 border-gray-300 transition group-hover:border-green-500 group-hover:bg-green-50 group-active:border-green-500 group-active:bg-green-50"></span></button>
                    @else
                        <span class="-m-2.5 flex h-11 w-11 shrink-0 items-center justify-center"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-700"><x-icon name="check" class="h-3.5 w-3.5" /></span></span>
                    @endif

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold {{ $task->status->isOpen() ? 'text-navy-900' : 'text-gray-500 line-through' }}">{{ $task->title }}</span>
                        <span class="block text-xs text-gray-500">
                            @if ($task->customer)@can('manage-crm')<a href="{{ route('customers.show', $task->customer) }}" class="font-medium text-klant-700">{{ $task->customer->name }}</a>@else{{ $task->customer->name }}@endcan, @endif
                            @if ($task->project)<a href="{{ route('projects.show', $task->project) }}" class="font-medium text-project-700">{{ $task->project->name }}</a>, @endif
                            {{ $task->owner?->name ?? 'geen eigenaar' }}@if ($task->deadline), <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ $task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M') }}</span>@endif{{ $task->note ? ', '.str($task->note)->limit(60) : '' }}
                        </span>
                    </span>

                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $task->priority->dotClasses() }}" title="{{ $task->priority->label() }}"></span>

                    @if ($task->status->isOpen())
                        <select
                            x-data
                            @change="patchJson('{{ route('tasks.status', $task) }}', { status: $event.target.value }).then(() => window.location.reload())"
                            class="hidden rounded-lg border-gray-200 py-1 pr-7 pl-2 text-xs text-gray-600 focus:border-brand-500 focus:ring-brand-500 sm:block">
                            @foreach (\App\Enums\TaskStatus::cases() as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected($statusOption === $task->status)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @empty
                <x-empty-state title="Geen taken in deze weergave" subtitle="Voeg een taak toe met de knop hierboven." icon="clipboard-check" category="planning" />
            @endforelse
        </div>
        @endnativeapp

        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    </div>

</x-layouts.app>
