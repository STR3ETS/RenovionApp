@props(['title' => null])

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700,800" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">

    @php
        $canCrm = auth()->user()->can('manage-crm');
        $aandachtCount = $canCrm ? \App\Services\AttentionService::cachedCount() : 0;
    @endphp

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-navy-950 text-white lg:flex">
        <div class="flex h-20 items-center px-6">
            <a href="{{ route('dashboard') }}">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-12 w-auto">
            </a>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-6">
            @php
                $items = $canCrm ? collect([
                    ['label' => 'Vandaag', 'icon' => 'home', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
                    ['label' => 'Aandacht', 'icon' => 'exclamation-triangle', 'route' => 'attention.index', 'active' => request()->routeIs('attention.*'), 'badge' => $aandachtCount],
                    ['label' => 'Aanvragen & Sales', 'icon' => 'inbox', 'route' => 'leads.index', 'active' => request()->routeIs('leads.*')],
                    config('renovion.modules.quotes') ? ['label' => 'Offertes', 'icon' => 'document-text', 'route' => 'quotes.index', 'active' => request()->routeIs('quotes.*')] : null,
                    ['label' => 'Projecten', 'icon' => 'building-office', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*')],
                    ['label' => 'Planning', 'icon' => 'calendar', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*')],
                    ['label' => 'Taken', 'icon' => 'clipboard-check', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*')],
                    ['label' => 'Klanten', 'icon' => 'users', 'route' => 'customers.index', 'active' => request()->routeIs('customers.*')],
                    config('renovion.modules.automations') ? ['label' => 'Automations', 'icon' => 'bolt', 'route' => 'automations.index', 'active' => request()->routeIs('automations.*')] : null,
                    auth()->user()->can('manage-team') ? ['label' => 'Team', 'icon' => 'user', 'route' => 'team.index', 'active' => request()->routeIs('team.*')] : null,
                ])->filter() : collect([
                    ['label' => 'Vandaag', 'icon' => 'home', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
                    ['label' => 'Projecten', 'icon' => 'building-office', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*')],
                    ['label' => 'Planning', 'icon' => 'calendar', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*')],
                    ['label' => 'Taken', 'icon' => 'clipboard-check', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*')],
                ]);
            @endphp

            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $item['active'] ? 'bg-brand-500 text-white shadow-sm' : 'text-navy-100 hover:bg-navy-900 hover:text-white' }}">
                    <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $item['active'] ? 'text-white' : 'text-navy-300' }}" />
                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                    @if (($item['badge'] ?? 0) > 0)
                        <span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="border-t border-navy-800 p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-800 text-xs font-bold text-navy-100">
                        {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-navy-300">{{ auth()->user()->role->label() }}</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-navy-300 transition hover:bg-navy-900 hover:text-white" title="Uitloggen">
                        <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Mobiele header --}}
    <header class="sticky top-0 z-30 flex h-14 items-center justify-between bg-navy-950 px-4 text-white lg:hidden">
        <a href="{{ route('dashboard') }}">
            <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-8 w-auto">
        </a>
        @if ($title)
            <span class="absolute left-1/2 -translate-x-1/2 text-sm font-semibold">{{ $title }}</span>
        @endif
        <div class="flex items-center gap-1">
            @if ($canCrm)
                <a href="{{ route('search') }}" class="rounded-lg p-2 text-navy-300" title="Zoeken">
                    <x-icon name="magnifying-glass" class="h-5 w-5" />
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-2 text-navy-300" title="Uitloggen">
                    <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                </button>
            </form>
        </div>
    </header>

    <div class="lg:pl-64">
        {{-- Desktop topbar: zoeken + snel aanmaken --}}
        <header class="sticky top-0 z-30 hidden h-16 items-center gap-4 border-b border-gray-200 bg-white px-8 lg:flex">
            @if ($canCrm)
                <form action="{{ route('search') }}" method="GET" class="relative w-full max-w-md">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                           placeholder="Zoek klanten, projecten, aanvragen…"
                           class="w-full rounded-xl border-gray-200 bg-gray-50 py-2 pl-9 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                </form>

                <div class="ml-auto flex items-center gap-3">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false"
                                class="flex items-center gap-1.5 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                            <x-icon name="plus" class="h-4 w-4" />
                            Nieuw
                            <x-icon name="chevron-down" class="h-3.5 w-3.5" />
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.right
                             class="absolute right-0 z-40 mt-2 w-48 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('leads.create') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-navy-900 hover:bg-gray-50"><x-icon name="inbox" class="h-4 w-4 text-gray-400" /> Nieuwe aanvraag</a>
                            <a href="{{ route('projects.create') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-navy-900 hover:bg-gray-50"><x-icon name="building-office" class="h-4 w-4 text-gray-400" /> Nieuw project</a>
                            <a href="{{ route('tasks.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-navy-900 hover:bg-gray-50"><x-icon name="clipboard-check" class="h-4 w-4 text-gray-400" /> Nieuwe taak</a>
                        </div>
                    </div>
                </div>
            @endif
        </header>

        {{-- Content --}}
        <main class="px-4 pt-4 pb-28 lg:px-8 lg:pt-6 lg:pb-12">
            {{-- Flash messages --}}
            @if (session('success') || session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-cloak
                     class="mb-4 flex items-center justify-between rounded-xl px-4 py-3 text-sm font-medium {{ session('error') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                    <span>{{ session('error') ?? session('success') }}</span>
                    <button @click="show = false" class="ml-3 opacity-60 hover:opacity-100" title="Sluiten"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Nova AI-assistent --}}
    @can('manage-crm')
    <div x-data="nova('{{ route('nova.propose') }}', '{{ route('nova.execute') }}')">
        <button @click="openPanel()"
                class="fixed right-4 bottom-24 z-40 rounded-full shadow-lg shadow-navy-950/30 transition hover:scale-105 active:scale-95 lg:bottom-8"
                title="Nova">
            <x-nova-avatar class="h-14 w-14 ring-2 ring-brand-500" />
            <span class="absolute -right-0.5 -bottom-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white ring-2 ring-white">
                <x-icon name="microphone" class="h-3.5 w-3.5" />
            </span>
        </button>

        <div x-show="open" x-cloak @click.self="open = false" @keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-end justify-center bg-navy-950/60 backdrop-blur-sm lg:items-center">
            <div class="flex h-[85dvh] w-full max-w-lg flex-col rounded-t-2xl bg-white lg:h-[70vh] lg:rounded-2xl" @click.stop>

                {{-- Header --}}
                <div class="flex items-center gap-3 border-b border-gray-100 p-4">
                    <x-nova-avatar class="ring-2 ring-brand-500" />
                    <div class="flex-1">
                        <p class="font-bold text-navy-900">Nova</p>
                        <p class="text-xs text-gray-500">Stelt voor — jij bevestigt</p>
                    </div>
                    <button @click="open = false" class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" title="Sluiten">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>

                {{-- Berichten --}}
                <div x-ref="novaMessages" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <template x-for="(message, index) in messages" :key="index">
                        <div class="flex gap-2" :class="message.role === 'user' ? 'justify-end' : ''">
                            <template x-if="message.role === 'nova'">
                                <img src="{{ asset('nova.jpg') }}" alt="Nova" class="mt-0.5 h-8 w-8 shrink-0 rounded-full object-cover">
                            </template>

                            <div class="max-w-[85%]">
                                <div :class="message.role === 'user' ? 'bg-navy-950 text-white' : 'bg-gray-100 text-navy-900'"
                                     class="rounded-2xl px-4 py-2.5 text-sm whitespace-pre-line">
                                    <span x-text="message.text"></span>
                                    <template x-if="message.url">
                                        <a :href="message.url" class="mt-1 block text-xs font-semibold text-brand-600 hover:text-brand-500">Bekijken →</a>
                                    </template>
                                </div>

                                {{-- Actievoorstel met bevestiging --}}
                                <template x-if="message.proposal">
                                    <div class="mt-2 rounded-2xl border border-brand-200 bg-brand-50 p-3">
                                        <p class="text-sm font-medium text-navy-900" x-text="message.proposal.preview"></p>
                                        <div class="mt-3 flex gap-2" x-show="message.proposal.state === 'open' || message.proposal.state === 'busy'">
                                            <button @click="confirm(message)" :disabled="message.proposal.state === 'busy'"
                                                    class="flex-1 rounded-lg bg-brand-500 py-2 text-xs font-bold text-white transition hover:bg-brand-600 disabled:opacity-50">
                                                <span x-text="message.proposal.state === 'busy' ? 'Bezig…' : 'Bevestigen'"></span>
                                            </button>
                                            <button @click="cancel(message)" :disabled="message.proposal.state === 'busy'"
                                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50">
                                                Annuleren
                                            </button>
                                        </div>
                                        <p x-show="message.proposal.state === 'done'" class="mt-2 flex items-center gap-1 text-xs font-semibold text-green-700"><x-icon name="check" class="h-3.5 w-3.5" /> Uitgevoerd</p>
                                        <p x-show="message.proposal.state === 'cancelled'" class="mt-2 text-xs font-semibold text-gray-400">Geannuleerd</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div x-show="busy" class="flex items-center gap-2">
                        <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-8 w-8 shrink-0 rounded-full object-cover">
                        <span class="flex items-center gap-2 rounded-2xl bg-gray-100 px-4 py-2.5 text-xs text-gray-500">
                            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-brand-500"></span>
                            Nova denkt na…
                        </span>
                    </div>
                </div>

                {{-- Invoer --}}
                <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-gray-100 p-3">
                    <button type="button" @click="toggleMic()" x-show="speechSupported"
                            :class="listening ? 'bg-red-500 text-white animate-pulse' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition"
                            :title="listening ? 'Stop met luisteren' : 'Spreek je opdracht in'">
                        <x-icon name="microphone" class="h-5 w-5" />
                    </button>
                    <input x-ref="novaInput" x-model="input" type="text" :disabled="busy"
                           placeholder="Typ of spreek je opdracht…"
                           class="flex-1 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <button type="submit" :disabled="busy || !input.trim()"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600 disabled:opacity-40" title="Versturen">
                        <x-icon name="paper-airplane" class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endcan

    {{-- Mobiele bottom-nav --}}
    @php
        $tabs = $canCrm ? [
            ['label' => 'Vandaag', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard'), 'icon' => 'home'],
            ['label' => 'Aanvragen', 'route' => 'leads.index', 'active' => request()->routeIs('leads.*'), 'icon' => 'inbox'],
            ['label' => 'Planning', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*'), 'icon' => 'calendar'],
            ['label' => 'Projecten', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*'), 'icon' => 'building-office'],
            ['label' => 'Acties', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*'), 'icon' => 'clipboard-check'],
        ] : [
            ['label' => 'Vandaag', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard'), 'icon' => 'home'],
            ['label' => 'Projecten', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*'), 'icon' => 'building-office'],
            ['label' => 'Planning', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*'), 'icon' => 'calendar'],
            ['label' => 'Acties', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*'), 'icon' => 'clipboard-check'],
        ];
    @endphp
    <nav class="fixed inset-x-0 bottom-0 z-40 grid border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden {{ count($tabs) === 5 ? 'grid-cols-5' : 'grid-cols-4' }}">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="flex flex-col items-center gap-0.5 py-2 {{ $tab['active'] ? 'text-brand-600' : 'text-gray-400' }}">
                <x-icon :name="$tab['icon']" class="h-6 w-6" />
                <span class="text-[10px] font-semibold">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </nav>

</body>
</html>
