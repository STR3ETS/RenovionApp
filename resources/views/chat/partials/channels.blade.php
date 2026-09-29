{{-- Kanalenlijst (mockup §18): Team en Projecten met unread counters. --}}
@php
    $teamKanalen = $channels->where('type', 'team');
    $projectKanalen = $channels->where('type', 'project');
@endphp

<div class="space-y-4">
    <div>
        <p class="mb-1.5 px-1 text-xs font-bold tracking-wide text-gray-400 uppercase">Team</p>
        <div class="space-y-1">
            @foreach ($teamKanalen as $kanaal)
                @php $unread = $kanaal->unreadCountFor($user); @endphp
                <a href="{{ route('chat.show', $kanaal) }}"
                   class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 transition {{ ($active ?? null) === $kanaal->id ? 'bg-brand-50 ring-1 ring-brand-200' : 'hover:bg-gray-50' }}">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-100 text-xs font-bold text-navy-700">{{ str($kanaal->name)->substr(0, 1)->upper() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-navy-900">{{ $kanaal->displayName() }}</span>
                        @if ($kanaal->last_message_at)
                            <span class="block text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($kanaal->last_message_at)->translatedFormat('j M H:i') }}</span>
                        @endif
                    </span>
                    @if ($unread > 0)
                        <span class="rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $unread }}</span>
                    @endif
                </a>
            @endforeach
        </div>
        @can('manage-crm')
            <form method="POST" action="{{ route('chat.channels.store') }}" class="mt-2 flex gap-1.5 px-1">
                @csrf
                <input type="text" name="name" required maxlength="60" placeholder="+ Nieuw kanaal"
                       class="min-w-0 flex-1 rounded-lg border-gray-200 bg-gray-50 py-1.5 text-xs placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                <button type="submit" class="rounded-lg bg-gray-100 px-2.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-200">OK</button>
            </form>
        @endcan
    </div>

    @if ($projectKanalen->isNotEmpty())
        <div>
            <p class="mb-1.5 px-1 text-xs font-bold tracking-wide text-gray-400 uppercase">Projecten</p>
            <div class="space-y-1">
                @foreach ($projectKanalen as $kanaal)
                    @php $unread = $kanaal->unreadCountFor($user); @endphp
                    <a href="{{ route('chat.show', $kanaal) }}"
                       class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 transition {{ ($active ?? null) === $kanaal->id ? 'bg-brand-50 ring-1 ring-brand-200' : 'hover:bg-gray-50' }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-navy-700 to-navy-950 text-white">
                            <x-icon name="building-office" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $kanaal->displayName() }}</span>
                            @if ($kanaal->last_message_at)
                                <span class="block text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($kanaal->last_message_at)->translatedFormat('j M H:i') }}</span>
                            @endif
                        </span>
                        @if ($unread > 0)
                            <span class="rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $unread }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
