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

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-navy-900 text-white lg:flex">
        <div class="flex h-20 items-center px-6">
            <a href="{{ route('dashboard') }}">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-12 w-auto">
            </a>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-6">
            @php
                $canCrm = auth()->user()->can('manage-crm');
                $aandachtCount = $canCrm ? \App\Services\AttentionService::cachedCount() : 0;

                $items = $canCrm ? collect([
                    ['label' => 'Vandaag', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
                    ['label' => 'Aandacht', 'route' => 'attention.index', 'active' => request()->routeIs('attention.*'), 'badge' => $aandachtCount],
                    ['label' => 'Aanvragen & Sales', 'route' => 'leads.index', 'active' => request()->routeIs('leads.*')],
                    config('renovion.modules.quotes') ? ['label' => 'Offertes', 'route' => 'quotes.index', 'active' => request()->routeIs('quotes.*')] : null,
                    ['label' => 'Projecten', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*')],
                    ['label' => 'Planning', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*')],
                    ['label' => 'Taken', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*')],
                    ['label' => 'Klanten', 'route' => 'customers.index', 'active' => request()->routeIs('customers.*')],
                    config('renovion.modules.automations') ? ['label' => 'Automations', 'route' => 'automations.index', 'active' => request()->routeIs('automations.*')] : null,
                    auth()->user()->can('manage-team') ? ['label' => 'Team', 'route' => 'team.index', 'active' => request()->routeIs('team.*')] : null,
                ])->filter() : collect([
                    ['label' => 'Vandaag', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
                    ['label' => 'Projecten', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*')],
                    ['label' => 'Planning', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*')],
                    ['label' => 'Taken', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*')],
                ]);
            @endphp

            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $item['active'] ? 'bg-brand-600 text-white' : 'text-navy-100 hover:bg-navy-800 hover:text-white' }}">
                    {{ $item['label'] }}
                    @if (($item['badge'] ?? 0) > 0)
                        <span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="border-t border-navy-800 p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-navy-300">{{ auth()->user()->role->label() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-navy-300 transition hover:bg-navy-800 hover:text-white" title="Uitloggen">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Mobiele header --}}
    <header class="sticky top-0 z-30 flex h-14 items-center justify-between bg-navy-900 px-4 text-white lg:hidden">
        <a href="{{ route('dashboard') }}">
            <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-8 w-auto">
        </a>
        @if ($title)
            <span class="absolute left-1/2 -translate-x-1/2 text-sm font-semibold">{{ $title }}</span>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg p-2 text-navy-300" title="Uitloggen">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
            </button>
        </form>
    </header>

    {{-- Content --}}
    <main class="px-4 pt-4 pb-28 lg:pt-8 lg:pr-8 lg:pb-12 lg:pl-72">
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

    {{-- Nova AI-assistent --}}
    @can('manage-crm')
    <div x-data="nova('{{ route('nova.propose') }}', '{{ route('nova.execute') }}')">
        <button @click="openPanel()"
                class="fixed right-4 bottom-24 z-40 rounded-full shadow-lg shadow-navy-900/30 transition hover:scale-105 active:scale-95 lg:bottom-8"
                title="Nova">
            <x-nova-avatar class="h-14 w-14 ring-2 ring-brand-600" />
            <span class="absolute -right-0.5 -bottom-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white ring-2 ring-white">
                <x-icon name="microphone" class="h-3.5 w-3.5" />
            </span>
        </button>

        <div x-show="open" x-cloak @click.self="open = false" @keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-end justify-center bg-navy-900/60 backdrop-blur-sm lg:items-center">
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
                                <div :class="message.role === 'user' ? 'bg-navy-900 text-white' : 'bg-gray-100 text-navy-900'"
                                     class="rounded-2xl px-4 py-2.5 text-sm whitespace-pre-line">
                                    <span x-text="message.text"></span>
                                    <template x-if="message.url">
                                        <a :href="message.url" class="mt-1 block text-xs font-semibold text-steel-600 hover:text-steel-500">Bekijken →</a>
                                    </template>
                                </div>

                                {{-- Actievoorstel met bevestiging --}}
                                <template x-if="message.proposal">
                                    <div class="mt-2 rounded-2xl border border-brand-200 bg-brand-50 p-3">
                                        <p class="text-sm font-medium text-navy-900" x-text="message.proposal.preview"></p>
                                        <div class="mt-3 flex gap-2" x-show="message.proposal.state === 'open' || message.proposal.state === 'busy'">
                                            <button @click="confirm(message)" :disabled="message.proposal.state === 'busy'"
                                                    class="flex-1 rounded-lg bg-brand-600 py-2 text-xs font-bold text-white transition hover:bg-brand-500 disabled:opacity-50">
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
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white transition hover:bg-brand-500 disabled:opacity-40" title="Versturen">
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
            ['label' => 'Vandaag', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard'), 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
            ['label' => 'Aanvragen', 'route' => 'leads.index', 'active' => request()->routeIs('leads.*'), 'icon' => 'M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z'],
                ['label' => 'Planning', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*'), 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                ['label' => 'Projecten', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*'), 'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75m-9 7.5h15a2.25 2.25 0 0 0 2.25-2.25V4.5A2.25 2.25 0 0 0 19.5 2.25h-15A2.25 2.25 0 0 0 2.25 4.5v14.25A2.25 2.25 0 0 0 4.5 21Z'],
                ['label' => 'Acties', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*'), 'icon' => 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75'],
            ] : [
                ['label' => 'Vandaag', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard'), 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
                ['label' => 'Projecten', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*'), 'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75m-9 7.5h15a2.25 2.25 0 0 0 2.25-2.25V4.5A2.25 2.25 0 0 0 19.5 2.25h-15A2.25 2.25 0 0 0 2.25 4.5v14.25A2.25 2.25 0 0 0 4.5 21Z'],
                ['label' => 'Planning', 'route' => 'planning.index', 'active' => request()->routeIs('planning.*'), 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                ['label' => 'Acties', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*'), 'icon' => 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75'],
            ];
        @endphp
    <nav class="fixed inset-x-0 bottom-0 z-40 grid border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden {{ count($tabs) === 5 ? 'grid-cols-5' : 'grid-cols-4' }}">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="flex flex-col items-center gap-0.5 py-2 {{ $tab['active'] ? 'text-brand-600' : 'text-gray-400' }}">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}" /></svg>
                <span class="text-[10px] font-semibold">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </nav>

</body>
</html>
