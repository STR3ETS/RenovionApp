{{-- De gesprekkenlijst (prototype, WhatsApp-stijl): zoeken, filters, en per
     gesprek het laatste bericht met het moment en het aantal ongelezen. --}}
@php
    $rijen = $channels->map(function ($kanaal) use ($user) {
        $preview = $kanaal->preview($user);

        return [
            'kanaal' => $kanaal,
            'preview' => $preview,
            'unread' => $kanaal->unreadCountFor($user),
            'project' => $kanaal->isProjectChannel(),
            // Waarop het zoekveld zoekt: de naam en het laatste bericht.
            'zoek' => mb_strtolower($kanaal->handle().' '.$kanaal->displayName().' '.$preview['text']),
        ];
    })->sortByDesc(fn ($rij) => $rij['kanaal']->last_message_at ?? '')->values();
@endphp

<aside class="wa-list" x-data="{ f: 'alle', q: '', nieuw: false }">
    <div class="wa-lh">
        <h3>Chats</h3>
        @can('manage-crm')
            <button type="button" class="iconbtn" @click="nieuw = ! nieuw; $nextTick(() => $refs.kanaalnaam?.focus())" title="Nieuw kanaal" aria-label="Nieuw kanaal"><x-icon name="plus" /></button>
        @endcan
    </div>

    @can('manage-crm')
        <form method="POST" action="{{ route('chat.channels.store') }}" class="mx-3 mb-2 flex gap-2" x-show="nieuw" x-cloak>
            @csrf
            <input type="text" name="name" x-ref="kanaalnaam" required maxlength="60" placeholder="Naam van het kanaal" class="h-9 min-w-0 flex-1 py-0 text-sm">
            <button type="submit" class="btn pri sm">Maken</button>
        </form>
    @endcan

    <div class="wa-search">
        <x-icon name="magnifying-glass" />
        <input type="search" x-model="q" placeholder="Zoek gesprek of bericht" aria-label="Zoeken in chats">
    </div>

    <div class="wa-f">
        @foreach (['alle' => 'Alles', 'ongelezen' => 'Ongelezen', 'projecten' => 'Projecten', 'teams' => 'Teams'] as $key => $label)
            <button type="button" :class="f === '{{ $key }}' && 'on'" @click="f = '{{ $key }}'">{{ $label }}</button>
        @endforeach
    </div>

    <div class="wa-items">
        @forelse ($rijen as $rij)
            @php $kanaal = $rij['kanaal']; @endphp
            <a href="{{ route('chat.show', $kanaal) }}" class="wa-it {{ ($active ?? null) === $kanaal->id ? 'on' : '' }}"
               x-show="(f === 'alle' || (f === 'ongelezen' && {{ $rij['unread'] > 0 ? 'true' : 'false' }}) || (f === 'projecten' && {{ $rij['project'] ? 'true' : 'false' }}) || (f === 'teams' && {{ $rij['project'] ? 'false' : 'true' }})) && (q === '' || @js($rij['zoek']).includes(q.toLowerCase()))">
                <span class="wa-av" style="--ac: {{ $rij['project'] ? '#d98a0b' : '#3e6ba8' }}"><x-icon :name="$rij['project'] ? 'folder' : 'hashtag'" /></span>
                <span class="wa-itx">
                    <span class="flex items-center justify-between gap-2">
                        <b>{{ $kanaal->handle() }}</b>
                        <small class="shrink-0 {{ $rij['unread'] > 0 ? 'u' : '' }}">{{ $rij['preview']['time'] }}</small>
                    </span>
                    <span class="flex items-center justify-between gap-2">
                        <small>{{ $rij['preview']['text'] }}</small>
                        @if ($rij['unread'] > 0)
                            <span class="wa-u">{{ $rij['unread'] }}</span>
                        @endif
                    </span>
                </span>
            </a>
        @empty
            <p class="px-4 py-8 text-center text-sm text-muted">Nog geen gesprekken.</p>
        @endforelse
    </div>
</aside>
