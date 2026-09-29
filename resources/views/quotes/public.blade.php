<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Offerte {{ $quote->number }} — Renovion</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700,800" rel="stylesheet">

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">

    <div class="mx-auto max-w-3xl px-4 py-6 lg:py-10">

        @if (session('success'))
            <div class="mb-4 rounded-xl bg-green-100 px-4 py-3 text-sm font-medium text-green-800 print:hidden">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm font-medium text-red-800 print:hidden">{{ session('error') }}</div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm print:shadow-none">
            {{-- Statusbanner --}}
            @if ($quote->status === \App\Enums\QuoteStatus::Akkoord)
                <div class="flex items-center gap-2 bg-green-600 px-6 py-3 text-sm font-semibold text-white lg:px-10">
                    <x-icon name="check" class="h-4 w-4" />
                    Ondertekend door {{ $quote->signed_name ?? $quote->customer->name }} op {{ ($quote->signed_at ?? $quote->accepted_at)->translatedFormat('j F Y') }} — bedankt voor uw vertrouwen!
                </div>
            @elseif ($quote->status === \App\Enums\QuoteStatus::Afgewezen)
                <div class="bg-gray-200 px-6 py-3 text-sm font-semibold text-gray-600 lg:px-10">Deze offerte is afgewezen.</div>
            @elseif ($quote->isExpired())
                <div class="bg-amber-100 px-6 py-3 text-sm font-semibold text-amber-800 lg:px-10">De geldigheidsdatum is verstreken. Neem contact met ons op voor een actuele versie.</div>
            @endif

            {{-- Document --}}
            <div class="px-6 py-8 lg:px-10">
                @include('quotes.partials.document', ['quote' => $quote])
            </div>

            {{-- Acties --}}
            @if ($quote->status->isOpen() && ! $quote->isExpired())
                <div class="border-t border-gray-100 bg-gray-50 px-6 py-8 lg:px-10 print:hidden" id="ondertekenen">
                    <h2 class="text-lg font-bold text-navy-950">Akkoord?</h2>
                    <p class="mt-1 mb-5 text-sm text-gray-500">Onderteken de offerte digitaal — wij nemen daarna direct contact met u op over de planning.</p>

                    <form method="POST" action="{{ route('quotes.public.sign', $quote->public_token) }}" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-5">
                        @csrf
                        <x-field label="Uw volledige naam" name="signed_name">
                            <input type="text" name="signed_name" id="signed_name" required value="{{ old('signed_name') }}" placeholder="{{ $quote->customer->name }}"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <label class="flex items-start gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="agree" value="1" required class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            Ik heb de offerte gelezen en ga akkoord met de werkzaamheden, het totaalbedrag en de voorwaarden.
                        </label>
                        @error('agree')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        <button type="submit" class="w-full rounded-xl bg-brand-500 py-3.5 text-base font-bold text-white transition hover:bg-brand-600 sm:w-auto sm:px-10">
                            Offerte ondertekenen
                        </button>
                        <p class="text-xs text-gray-400">Uw naam, datum, tijd en IP-adres worden vastgelegd als digitale ondertekening van versie v{{ $quote->version }}.</p>
                    </form>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <details class="rounded-2xl border border-gray-200 bg-white p-5">
                            <summary class="cursor-pointer text-sm font-semibold text-navy-900">Aanpassing aanvragen</summary>
                            <form method="POST" action="{{ route('quotes.public.change', $quote->public_token) }}" class="mt-3 space-y-2">
                                @csrf
                                <textarea name="message" rows="3" required placeholder="Wat wilt u aangepast zien?"
                                          class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                <button type="submit" class="rounded-xl border border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Versturen</button>
                            </form>
                        </details>
                        <details class="rounded-2xl border border-gray-200 bg-white p-5">
                            <summary class="cursor-pointer text-sm font-semibold text-navy-900">Offerte afwijzen</summary>
                            <form method="POST" action="{{ route('quotes.public.reject', $quote->public_token) }}" class="mt-3 space-y-2">
                                @csrf
                                <textarea name="reason" rows="3" placeholder="Mogen we vragen waarom? (niet verplicht)"
                                          class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                <button type="submit" class="rounded-xl border border-red-200 bg-white px-5 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50">Afwijzen</button>
                            </form>
                        </details>
                    </div>
                </div>
            @endif

            {{-- Footer --}}
            <div class="border-t border-gray-100 px-6 py-6 text-center text-xs text-gray-400 lg:px-10">
                <p class="font-semibold text-gray-500">Renovion — geen half werk en vage beloftes</p>
                <p class="mt-1">Mercatorweg 28, 6827 DC Arnhem · info@renovion.nl · +31 6 395 353 00 · KVK 95384782</p>
            </div>
        </div>

        <div class="mt-4 flex justify-center print:hidden">
            <button type="button" onclick="window.print()" class="flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <x-icon name="arrow-down-tray" class="h-4 w-4" /> Download als PDF
            </button>
        </div>
    </div>

</body>
</html>
