<x-layouts.app title="Taken">

    <div x-data="{ nieuw: false }">
        <x-page-header title="Taken" subtitle="{{ $tasks->total() }} taken in deze weergave">
            <button @click="nieuw = !nieuw" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500">+ Taak</button>
        </x-page-header>

        {{-- Quick add --}}
        <form x-show="nieuw" x-cloak method="POST" action="{{ route('tasks.store') }}"
              class="mb-4 grid gap-3 rounded-2xl border border-brand-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <input type="text" name="title" required placeholder="Wat moet er gebeuren? *"
                   class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2">
            <input type="date" name="deadline" value="{{ today()->toDateString() }}"
                   class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            <select name="priority" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach (\App\Enums\TaskPriority::cases() as $priority)
                    <option value="{{ $priority->value }}" @selected($priority === \App\Enums\TaskPriority::Normaal)>{{ $priority->label() }}</option>
                @endforeach
            </select>
            @can('manage-crm')
                <select name="owner_id" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Eigenaar: ikzelf</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <select name="customer_id" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Klant (optioneel) —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            @endcan
            <select name="project_id" class="rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">— Project (optioneel) —</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-navy-900 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 lg:col-span-2">Taak aanmaken</button>
        </form>

        {{-- Filters --}}
        <div class="mb-4 flex flex-wrap gap-1.5">
            <a href="{{ route('tasks.index') }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === null ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">Open</a>
            @foreach (\App\Enums\TaskStatus::cases() as $statusOption)
                <a href="{{ route('tasks.index', ['status' => $statusOption->value]) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === $statusOption ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">{{ $statusOption->label() }}</a>
            @endforeach
        </div>

        {{-- Takenlijst --}}
        <div class="space-y-2">
            @forelse ($tasks as $task)
                <div class="flex items-center gap-3 rounded-xl border bg-white p-3 {{ $task->isOverdue() ? 'border-red-300' : 'border-gray-200' }}">
                    @if ($task->status->isOpen())
                        <button
                            x-data
                            @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 transition hover:border-green-500 hover:bg-green-50"
                            title="Afronden"></button>
                    @else
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700"><x-icon name="check" class="h-3.5 w-3.5" /></span>
                    @endif

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold {{ $task->status->isOpen() ? 'text-navy-900' : 'text-gray-400 line-through' }}">{{ $task->title }}</span>
                        <span class="block text-xs text-gray-500">
                            @if ($task->customer)@can('manage-crm')<a href="{{ route('customers.show', $task->customer) }}" class="text-steel-600">{{ $task->customer->name }}</a>@else{{ $task->customer->name }}@endcan · @endif
                            @if ($task->project)<a href="{{ route('projects.show', $task->project) }}" class="text-steel-600">{{ $task->project->name }}</a> · @endif
                            {{ $task->owner?->name ?? 'geen eigenaar' }}
                            @if ($task->deadline) · <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ $task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M') }}</span> @endif
                            @if ($task->note) · {{ str($task->note)->limit(60) }} @endif
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
                <x-empty-state title="Geen taken in deze weergave" subtitle="Voeg een taak toe met de knop hierboven." />
            @endforelse
        </div>

        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    </div>

</x-layouts.app>
