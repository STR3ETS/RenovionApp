<!DOCTYPE html>
<html lang="nl" @nativeapp class="native-app" @endnativeapp>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Inloggen - {{ config('app.name') }}</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @include('partials.load-guard')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell flex min-h-screen items-center justify-center bg-surface-950 px-4 pt-safe pb-safe font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            @nativeapp
                <x-os-logo class="mx-auto h-24 w-auto" />
            @else
            <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="mx-auto h-20 w-auto">
            <p class="mt-2 text-[11px] font-bold tracking-widest text-brand-400 uppercase">Renovion Dash</p>
            @endnativeapp
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

            <label class="flex items-center gap-2 text-sm text-slate-300">
                {{-- In de iOS/Android-app (user agent "RenovionApp") staat dit standaard aan. --}}
                <input type="checkbox" name="remember" value="1" @checked(\App\Support\NativeApp::active())
                       class="rounded border-surface-600 text-brand-600 focus:ring-brand-500">
                Ingelogd blijven
            </label>

            <button type="submit"
                    class="w-full rounded-xl bg-brand-600 py-3 text-sm font-bold text-white transition hover:bg-brand-500">
                Inloggen
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">Innovatieve oplossingen voor elke renovatie</p>
        <p class="mt-2 text-center text-xs"><a href="{{ route('privacy') }}" class="tap text-slate-400 underline-offset-2 hover:text-white hover:underline">Privacyverklaring</a></p>
    </div>
</body>
</html>
