{{-- Dezelfde buitenschil voor het klantportaal en het partnerportaal: licht,
     smal en zonder het interne menu. --}}
@props(['title' => null, 'portal' => 'Klantportaal', 'home' => 'portal.index'])

@php
    // In de iOS/Android-app de Renovion OS-stijl en de pagina-opbouw van de app (html[data-motion]).
    $isApp = \App\Support\NativeApp::active();
@endphp

<!DOCTYPE html>
<html lang="nl" @if ($isApp) class="native-app" @if (auth()->user()?->motion_enabled !== false) data-motion @endif @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' - ' : '' }}{{ $portal }} Renovion</title>
    @include('partials.favicon')

    @include('partials.fonts')

    @include('partials.load-guard')
    @if ($isApp)
        @include('partials.motion-head')
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-gray-100 font-sans text-gray-900 antialiased">

    {{-- Sticky: anders schuift de pagina in de app onder de klok door en is de statusbalk onleesbaar. --}}
    <header class="sticky top-0 z-30 bg-navy-950 pt-safe text-white">
        <div class="mx-auto flex h-16 w-full max-w-3xl items-center justify-between px-4">
            <a href="{{ route($home) }}">
                @nativeapp
                    <x-os-logo class="h-12 w-auto" />
                @else
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
                @endnativeapp
            </a>
            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-navy-200 sm:block">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-navy-300 transition hover:bg-navy-900 hover:text-white" title="Uitloggen">
                        <x-icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6">
        @if (session('success') || session('error'))
            <div class="mb-4 rounded-xl px-4 py-3 text-sm font-medium {{ session('error') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                {{ session('error') ?? session('success') }}
            </div>
        @endif

        {{ $slot }}

        @if ($isApp)
            @include('partials.motion-stage')
        @endif
    </main>

    <footer class="border-t border-gray-200 bg-white pt-5 pb-[calc(1.25rem_+_env(safe-area-inset-bottom))] text-center text-xs text-gray-500">
        <p class="font-semibold text-gray-500">Renovion - geen half werk en vage beloftes</p>
        <p class="mt-1">Mercatorweg 28, 6827 DC Arnhem, info@renovion.nl, +31 6 395 353 00</p>
        <p class="mt-1"><a href="{{ route('privacy') }}" class="tap font-semibold text-gray-600 underline underline-offset-2">Privacyverklaring</a></p>
    </footer>

    <x-photo-viewer />

</body>
</html>
