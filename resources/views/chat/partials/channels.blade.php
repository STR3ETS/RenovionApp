{{-- Kanalenlijst: één lijst op laatste bericht, met wie wat zei en het aantal ongelezen. --}}
<div class="divide-y divide-gray-100">
    @foreach ($channels as $kanaal)
        @php
            $unread = $kanaal->unreadCountFor($user);
            $laatste = $kanaal->latestMessage;
        @endphp
        <a href="{{ route('chat.show', $kanaal) }}"
           class="flex min-h-[4.25rem] items-center gap-3 px-4 py-2.5 transition active:bg-gray-50 {{ ($active ?? null) === $kanaal->id ? 'bg-gray-100' : 'hover:bg-gray-50' }}">
            <x-chat-avatar :channel="$kanaal" class="h-11 w-11" />
            <span class="min-w-0 flex-1">
                <span class="flex items-baseline justify-between gap-3">
                    <span class="truncate text-sm font-bold text-navy-950">{{ $kanaal->displayName() }}</span>
                    @if ($laatste)
                        <span class="shrink-0 text-xs {{ $unread > 0 ? 'font-semibold text-brand-700' : 'text-gray-500' }}">{{ $laatste->listTime() }}</span>
                    @endif
                </span>
                <span class="mt-0.5 flex items-center justify-between gap-3">
                    <span class="truncate text-sm {{ $unread > 0 ? 'font-medium text-navy-900' : 'text-gray-500' }}">{{ $laatste?->preview($user) ?? 'Nog geen berichten' }}</span>
                    @if ($unread > 0)
                        <span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-brand-500 px-1.5 text-[11px] font-bold text-white">{{ $unread }}</span>
                    @endif
                </span>
            </span>
        </a>
    @endforeach
</div>

@can('manage-crm')
    {{-- Nieuw kanaal: ingeklapt een knop, open één afgebakend blok --}}
    <div x-data="{ adding: false }" class="border-t border-gray-100 p-3">
        <button type="button" x-show="!adding" @click="adding = true; $nextTick(() => $refs.naam.focus())"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-300 py-2.5 text-sm font-semibold text-gray-500 transition hover:border-brand-400 hover:text-brand-700 active:bg-gray-50">
            <x-icon name="plus" class="h-4 w-4" /> Nieuw kanaal
        </button>

        <form method="POST" action="{{ route('chat.channels.store') }}" x-show="adding" x-cloak class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
            @csrf
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-gray-600">Naam van het kanaal</span>
                <input x-ref="naam" type="text" name="name" required maxlength="60" placeholder="Bijv. Werkvoorbereiding"
                       class="w-full rounded-xl border-gray-200 bg-white text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
            </label>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Toevoegen</button>
                <button type="button" @click="adding = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</button>
            </div>
        </form>
    </div>
@endcan
