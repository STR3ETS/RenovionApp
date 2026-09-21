<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-navy-900 px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="mx-auto h-20 w-auto">
            <p class="mt-2 text-sm font-medium text-navy-300">Renovion Dash</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-xl">
            @csrf

            <x-field label="E-mailadres" name="email">
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </x-field>

            <x-field label="Wachtwoord" name="password">
                <input type="password" name="password" id="password" required
                       class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </x-field>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                Ingelogd blijven
            </label>

            <button type="submit"
                    class="w-full rounded-xl bg-brand-600 py-3 text-sm font-bold text-white transition hover:bg-brand-500">
                Inloggen
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-navy-400">Innovatieve oplossingen voor elke renovatie</p>
    </div>
</body>
</html>
