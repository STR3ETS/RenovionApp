{{-- Kanalenlijst van de iOS/Android-app (prototype Renovion OS): zoeken en filteren gebeurt hier in de browser. --}}
<div class="app-chat-list" x-data="{
        filter: 'alles',
        zoek: '',
        adding: false,
        zichtbaar(naam, soort, ongelezen) {
            if (this.zoek && ! naam.toLowerCase().includes(this.zoek.toLowerCase())) return false;
            return this.filter === 'alles' || this.filter === soort || (this.filter === 'ongelezen' && ongelezen > 0);
        },
     }">
    <div class="app-chat-head">
        <h1>Chats</h1>
        @can('manage-crm')
            <button type="button" @click="adding = !adding; adding && $nextTick(() => $refs.naam.focus())" class="app-iconbtn tap" title="Nieuw kanaal" aria-label="Nieuw kanaal">
                <x-icon name="plus" class="h-[18px] w-[18px]" />
            </button>
        @endcan
    </div>

    @can('manage-crm')
        <form method="POST" action="{{ route('chat.channels.store') }}" x-show="adding" x-cloak class="mb-3 space-y-3 rounded-xl bg-white p-3">
            @csrf
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-gray-600">Naam van het kanaal</span>
                <input x-ref="naam" type="text" name="name" required maxlength="60" placeholder="Bijv. Werkvoorbereiding" class="w-full bg-white placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
            </label>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white active:bg-brand-600">Toevoegen</button>
                <button type="button" @click="adding = false" class="app-btn">Annuleren</button>
            </div>
        </form>
    @endcan

    <label class="relative mb-2.5 block">
        <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-500" />
        <input type="search" x-model="zoek" aria-label="Zoek gesprek" placeholder="Zoek gesprek" class="w-full !rounded-[12px] !border-transparent bg-white pl-10 placeholder:text-gray-500 focus:border-brand-500 focus:ring-brand-500">
    </label>

    <div class="-mx-3 mb-2.5 flex gap-1.5 overflow-x-auto px-3 pb-1">
        <button type="button" @click="filter = 'alles'" :class="filter === 'alles' ? 'bg-navy-950 text-white' : 'bg-white text-navy-900'" class="h-8 shrink-0 rounded-full px-3 text-[13px] font-semibold">Alles</button>
        <button type="button" @click="filter = 'ongelezen'" :class="filter === 'ongelezen' ? 'bg-navy-950 text-white' : 'bg-white text-navy-900'" class="h-8 shrink-0 rounded-full px-3 text-[13px] font-semibold">Ongelezen</button>
        <button type="button" @click="filter = 'project'" :class="filter === 'project' ? 'bg-navy-950 text-white' : 'bg-white text-navy-900'" class="h-8 shrink-0 rounded-full px-3 text-[13px] font-semibold">Projecten</button>
        <button type="button" @click="filter = 'team'" :class="filter === 'team' ? 'bg-navy-950 text-white' : 'bg-white text-navy-900'" class="h-8 shrink-0 rounded-full px-3 text-[13px] font-semibold">Teams</button>
    </div>

    @foreach ($channels as $kanaal)
        @php
            $unread = $kanaal->unreadCountFor($user);
            $laatste = $kanaal->latestMessage;
        @endphp
        <a href="{{ route('chat.show', $kanaal) }}" class="app-chat-item"
           x-show="zichtbaar(@js($kanaal->displayName()), @js($kanaal->isProjectChannel() ? 'project' : 'team'), {{ $unread }})">
            <x-chat-avatar :channel="$kanaal" />
            <span class="min-w-0 flex-1">
                <span class="flex items-baseline justify-between gap-3">
                    <span class="truncate text-sm font-bold text-navy-950">{{ $kanaal->displayName() }}</span>
                    @if ($laatste)
                        <span class="shrink-0 text-xs {{ $unread > 0 ? 'font-semibold text-brand-700' : 'text-gray-500' }}">{{ $laatste->listTime() }}</span>
                    @endif
                </span>
                <span class="mt-0.5 flex items-center justify-between gap-3">
                    <span class="truncate text-[13px] {{ $unread > 0 ? 'font-medium text-navy-900' : 'text-gray-600' }}">{{ $laatste?->preview($user) ?? 'Nog geen berichten' }}</span>
                    @if ($unread > 0)
                        <span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-brand-500 px-1.5 text-[11px] font-bold text-white">{{ $unread }}</span>
                    @endif
                </span>
            </span>
        </a>
    @endforeach
</div>
