@php
    use App\Models\Setting;
    $eur = fn (float $v) => 'EUR '.number_format($v, 2, ',', '.');
    $btw = round((float) $extra->amount * $vatPct / 100, 2);
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Meerwerk {{ $extra->number }} - Renovion</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
{{-- Bewust licht: dit is voor de klant --}}
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    <div class="mx-auto max-w-xl px-4 py-6 lg:py-10">
        @if (session('success'))
            <div class="mb-4 rounded-xl bg-green-100 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div class="bg-navy-950 px-6 py-5">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
            </div>

            <div class="p-6">
                <p class="text-xs font-bold tracking-widest text-brand-600 uppercase">Meerwerk {{ $extra->number }}</p>
                <h1 class="mt-1 text-xl font-bold">{{ $extra->description }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Bij uw project {{ $project->name }}{{ $project->address ? ', '.$project->address : '' }}.
                </p>

                @if ($extra->details)
                    <p class="mt-4 text-sm whitespace-pre-line text-gray-700">{{ $extra->details }}</p>
                @endif

                <dl class="mt-5 space-y-1 rounded-xl bg-gray-50 p-4 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">Bedrag exclusief btw</dt><dd class="tabular-nums">{{ $eur((float) $extra->amount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Btw {{ rtrim(rtrim(number_format($vatPct, 1, ',', '.'), '0'), ',') }}%</dt><dd class="tabular-nums">{{ $eur($btw) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-bold"><dt>Totaal</dt><dd class="tabular-nums">{{ $eur((float) $extra->amount + $btw) }}</dd></div>
                </dl>

                <p class="mt-4 text-sm text-gray-600">
                    Dit meerwerk komt bovenop de overeengekomen opdracht. We voeren het pas uit na uw akkoord en factureren het apart.
                </p>

                @if ($extra->isApproved())
                    <div class="mt-5 flex items-center gap-2 rounded-xl bg-green-600 px-4 py-3 text-sm font-semibold text-white">
                        <x-icon name="check" class="h-4 w-4" />
                        Akkoord gegeven door {{ $extra->approved_name }} op {{ $extra->approved_at->translatedFormat('j F Y') }}.
                    </div>
                @else
                    <form method="POST" action="{{ route('extra-works.accept', $extra->public_token) }}" class="mt-5 space-y-3 border-t border-gray-200 pt-5">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700">Uw naam
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="200" autocomplete="name"
                                   class="mt-1 w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </label>
                        @error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="agree" value="1" required class="mt-0.5 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span>Ik ga akkoord met dit meerwerk voor {{ $eur((float) $extra->amount) }} exclusief btw.</span>
                        </label>
                        @error('agree')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                        <button type="submit" class="w-full rounded-full bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600">Akkoord geven</button>
                    </form>
                @endif
            </div>

            <div class="bg-gray-50 px-6 py-4 text-xs text-gray-500">
                {{ Setting::get('bedrijf.naam') }}, {{ Setting::get('bedrijf.adres') }}, {{ Setting::get('bedrijf.postcode') }}, {{ Setting::get('bedrijf.email') }}, {{ Setting::get('bedrijf.telefoon') }}
            </div>
        </div>
    </div>
</body>
</html>
