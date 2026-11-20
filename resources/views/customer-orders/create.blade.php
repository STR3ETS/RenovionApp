@php
    use App\Models\Setting;
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Opdracht doorgeven - Renovion</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|plus-jakarta-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
{{-- Bewust licht: dit is voor de klant --}}
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    <div class="mx-auto max-w-xl px-4 py-6 lg:py-10">
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div class="bg-navy-950 px-6 py-5">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
            </div>

            <div class="p-6">
                <p class="text-xs font-bold tracking-widest text-brand-600 uppercase">Opdracht doorgeven</p>
                <h1 class="mt-1 text-xl font-bold">{{ $customer->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">Klantnummer {{ $customer->number }}. Deze link is persoonlijk: deel hem alleen binnen uw eigen organisatie.</p>

                @if (session('success'))
                    <div class="mt-5 flex items-start gap-2 rounded-xl bg-green-600 px-4 py-3 text-sm font-semibold text-white">
                        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ session('success') }}</span>
                    </div>
                    <p class="mt-4 text-sm text-gray-600">Nog een opdracht doorgeven? Vul het formulier hieronder opnieuw in.</p>
                @endif

                <form method="POST" action="{{ route('customer-orders.store', $token) }}" class="mt-5 space-y-4">
                    @csrf

                    <label class="block text-sm font-medium text-gray-700">Uw naam
                        <input type="text" name="contact" value="{{ old('contact', $customer->contact_name) }}" required maxlength="255" autocomplete="name"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </label>
                    @error('contact')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    <label class="block text-sm font-medium text-gray-700">Adres van het werk
                        <input type="text" name="address" value="{{ old('address') }}" required maxlength="255" placeholder="Straat, huisnummer, postcode en plaats"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </label>
                    @error('address')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    <label class="block text-sm font-medium text-gray-700">Wat moet er gebeuren
                        <textarea name="work" rows="5" required maxlength="5000" placeholder="Bijvoorbeeld: wanden en plafond van de hal sausklaar stucen, ongeveer 60 m2"
                                  class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('work') }}</textarea>
                    </label>
                    @error('work')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    <label class="block text-sm font-medium text-gray-700">Wanneer moet het klaar zijn
                        <input type="date" name="deadline" value="{{ old('deadline') }}" min="{{ today()->toDateString() }}"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </label>
                    @error('deadline')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    {{-- Vangt formulierrobots: mensen zien dit veld niet --}}
                    <div class="hidden" aria-hidden="true">
                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <button type="submit" class="w-full rounded-full bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600">Opdracht versturen</button>
                </form>
            </div>

            <div class="bg-gray-50 px-6 py-4 text-xs text-gray-500">
                {{ Setting::get('bedrijf.naam') }}, {{ Setting::get('bedrijf.adres') }}, {{ Setting::get('bedrijf.postcode') }}, {{ Setting::get('bedrijf.email') }}, {{ Setting::get('bedrijf.telefoon') }}
            </div>
        </div>
    </div>
</body>
</html>
