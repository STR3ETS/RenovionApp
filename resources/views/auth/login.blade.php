<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|plus-jakarta-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell flex min-h-screen items-center justify-center bg-surface-950 px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <x-logo class="mx-auto w-60" />
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4 rounded-2xl border border-surface-700 bg-surface-800 p-6 shadow-2xl shadow-black/40">
            @csrf

            <x-field label="E-mailadres" name="email">
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
            </x-field>

            <x-field label="Wachtwoord" name="password">
                <input type="password" name="password" id="password" required
                       class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
            </x-field>

            <label class="flex items-center gap-2 text-sm text-ink2">
                <input type="checkbox" name="remember" value="1" class="rounded border-surface-600 text-brand-600 focus:ring-brand-500">
                Ingelogd blijven
            </label>

            <button type="submit"
                    class="btn pri lg w-full">
                Inloggen
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-faint">Innovatieve oplossingen voor elke renovatie</p>
    </div>
</body>
</html>
