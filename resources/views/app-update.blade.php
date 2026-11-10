{{-- Verplichte update (mobile/release.json, RequireAppUpdate): een app ouder dan minimum_build kan niet verder. --}}
<!DOCTYPE html>
<html lang="nl" @nativeapp class="native-app" @endnativeapp>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Update nodig - Renovion</title>
    @include('partials.favicon')
    @include('partials.fonts')
    {{-- Ook hier app.js: native.js verbergt het splashscreen en zet de statusbalk goed. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-navy-950 px-6 pt-safe pb-safe font-sans text-white antialiased">
    <main class="flex w-full max-w-sm flex-col items-center text-center">
        @nativeapp
            <x-os-logo class="mb-8 h-20 w-auto" />
        @else
        <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="mb-8 h-14 w-auto">
        @endnativeapp

        <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500/15 text-brand-300">
            <x-icon name="arrow-down-tray" class="h-7 w-7" />
        </span>
        <h1 class="text-2xl font-bold">Update nodig</h1>
        <p class="mt-2 text-sm leading-relaxed text-navy-200">Er is een nieuwe versie van Renovion OS. Download de nieuwste versie om verder te werken.</p>

        @if ($update['url'])
            <a href="{{ $update['url'] }}" class="mt-8 w-full rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">
                Nieuwste versie downloaden
            </a>
        @else
            <p class="mt-8 w-full rounded-xl bg-navy-900 px-4 py-3 text-sm text-navy-200">Vraag kantoor om de nieuwste versie van de app.</p>
        @endif

        <a href="{{ request()->fullUrl() }}" class="mt-3 text-sm font-semibold text-navy-300 underline-offset-2 hover:text-white hover:underline">Opnieuw controleren</a>
    </main>
</body>
</html>
