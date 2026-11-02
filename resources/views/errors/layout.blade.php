<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') - Renovion</title>
    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700,800" rel="stylesheet">

    {{-- Ook hier app.js: native.js verbergt het splashscreen en zet de statusbalk op wit-op-navy. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-navy-950 px-6 pt-safe pb-safe font-sans text-white antialiased">
    <main class="flex w-full max-w-sm flex-col items-center text-center">
        <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="mb-8 h-14 w-auto">

        <p class="text-xs font-bold tracking-widest text-brand-300 uppercase">@yield('code')</p>
        <h1 class="mt-2 text-2xl font-bold">@yield('title')</h1>
        <p class="mt-2 text-sm leading-relaxed text-navy-200">@yield('message')</p>

        <a href="@yield('href', url('/'))" class="mt-8 w-full rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">
            @yield('action', 'Terug naar Vandaag')
        </a>
    </main>
</body>
</html>
