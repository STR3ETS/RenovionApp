@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Werkzaamheden per ruimte" class="mb-1 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.intro" @blur="save()" data-ph="Korte inleiding (optioneel)" class="mb-4 text-sm text-gray-500"></div>
        <div class="space-y-3">
            <template x-for="(room, ri) in block.data.items" :key="ri">
                <div class="group/room relative rounded-xl bg-gray-50 p-5">
                    <div class="mb-3 flex items-center gap-2 border-b-2 border-brand-500 pb-2">
                        <div x-rich="room.name" @blur="save()" data-ph="Ruimte" class="min-w-0 flex-1 text-sm font-bold text-navy-900"></div>
                        <button type="button" @click="block.data.items.splice(ri, 1); save()" class="shrink-0 text-gray-300 opacity-0 transition group-hover/room:opacity-100 hover:text-red-500" title="Ruimte verwijderen">
                            <x-icon name="trash" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <template x-for="(task, ti) in room.tasks" :key="ti">
                        <div class="group/task mb-1.5 flex items-center gap-2">
                            <span class="h-1 w-1 shrink-0 rounded-full bg-brand-500"></span>
                            <div x-rich="room.tasks[ti]" @blur="save()" data-ph="Werkzaamheid…" class="min-w-0 flex-1 text-sm text-gray-600"></div>
                            <button type="button" @click="room.tasks.splice(ti, 1); save()" class="shrink-0 text-gray-300 opacity-0 transition group-hover/task:opacity-100 hover:text-red-500">
                                <x-icon name="x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="room.tasks.push('Nieuwe werkzaamheid'); save()" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-500">
                        <x-icon name="plus" class="h-3.5 w-3.5" /> Werkzaamheid toevoegen
                    </button>
                </div>
            </template>
            <button type="button" @click="block.data.items.push({ name: 'Nieuwe ruimte', tasks: ['Eerste werkzaamheid'] }); save()"
                    class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-300 p-4 text-xs font-semibold text-gray-400 transition hover:border-brand-400 hover:text-brand-600">
                <x-icon name="plus" class="h-3.5 w-3.5" /> Ruimte toevoegen
            </button>
        </div>
    @else
        <h2 class="mb-1 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Werkzaamheden per ruimte') !!}</h2>
        @if (filled($d['intro'] ?? ''))
            <div class="mb-4 text-sm text-gray-500">{!! QuoteText::render($d['intro']) !!}</div>
        @endif
        <div class="space-y-3">
            @foreach (($d['items'] ?? []) as $room)
                <div class="rounded-xl bg-gray-50 p-5">
                    <h3 class="mb-3 border-b-2 border-brand-500 pb-2 text-sm font-bold text-navy-900">{!! QuoteText::render($room['name'] ?? '') !!}</h3>
                    @foreach (($room['tasks'] ?? []) as $task)
                        @continue(blank($task))
                        <div class="mb-1.5 flex items-start gap-2">
                            <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span>
                            <span class="text-sm text-gray-600">{!! QuoteText::render($task) !!}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</section>
