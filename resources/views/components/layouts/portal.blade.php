@props(['title' => null])

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' — ' : '' }}Klantportaal Renovion</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700,800" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-gray-100 font-sans text-gray-900 antialiased">

    <header class="bg-navy-950 text-white">
        <div class="mx-auto flex h-16 w-full max-w-3xl items-center justify-between px-4">
            <a href="{{ route('portal.index') }}">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
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
    </main>

    <footer class="border-t border-gray-200 bg-white py-5 text-center text-xs text-gray-400">
        <p class="font-semibold text-gray-500">Renovion — geen half werk en vage beloftes</p>
        <p class="mt-1">Mercatorweg 28, 6827 DC Arnhem · info@renovion.nl · +31 6 395 353 00</p>
    </footer>

</body>
</html>
