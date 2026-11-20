@php
    use App\Models\Project;
    use App\Models\Setting;
    $eur = fn (float $v) => '€ '.number_format($v, 2, ',', '.');
    $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    $regie = $statement->isRegie();
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Urenstaat {{ $project->number() }} - Renovion</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|plus-jakarta-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
{{-- Bewust licht: dit gaat op papier en ter tekening naar de opdrachtgever --}}
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased print:bg-white">
    <div class="mx-auto max-w-3xl px-4 py-6 print:max-w-none print:p-0">
        <div class="mb-4 flex justify-end gap-2 print:hidden">
            <a href="{{ route('projects.show', [$project, 'tab' => 'uren']) }}" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">Terug</a>
            <button type="button" onclick="window.print()" class="rounded-full bg-brand-500 px-5 py-2 text-sm font-bold text-white">Afdrukken of opslaan als PDF</button>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-sm print:rounded-none print:p-0 print:shadow-none">
            <div class="flex items-start justify-between gap-6 border-b-4 border-brand-500 pb-5">
                <div>
                    <p class="text-xs font-bold tracking-widest text-brand-600 uppercase">Urenstaat</p>
                    <h1 class="mt-1 text-2xl font-bold">{{ $project->name }}</h1>
                    <p class="mt-1 text-sm text-gray-600">{{ collect([$project->address, $project->city])->filter()->join(', ') }}</p>
                </div>
                <div class="text-right text-sm text-gray-600">
                    <p class="font-bold text-gray-900">{{ Setting::get('bedrijf.naam') }}</p>
                    <p>{{ Setting::get('bedrijf.adres') }}</p>
                    <p>{{ Setting::get('bedrijf.postcode') }}</p>
                </div>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-gray-500">Projectnummer</dt><dd class="font-semibold">{{ $project->number() }}</dd></div>
                <div><dt class="text-gray-500">Opdrachtgever</dt><dd class="font-semibold">{{ $project->customer?->name }}</dd></div>
                <div><dt class="text-gray-500">Vorm</dt><dd class="font-semibold">{{ Project::FORMS[$project->contract_form] ?? $project->contract_form }}</dd></div>
                <div><dt class="text-gray-500">Datum</dt><dd class="font-semibold">{{ today()->translatedFormat('j F Y') }}</dd></div>
            </dl>

            <table class="mt-6 w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-300 text-left text-xs tracking-wider text-gray-500 uppercase">
                        <th class="py-2 pr-3">Datum</th>
                        <th class="py-2 pr-3">Wie</th>
                        <th class="py-2 pr-3">Omschrijving</th>
                        <th class="py-2 text-right">Uren</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statement->entries as $entry)
                        <tr class="border-b border-gray-200">
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $entry->date->translatedFormat('D j M Y') }}</td>
                            <td class="py-2 pr-3">{{ $entry->user?->name }}</td>
                            <td class="py-2 pr-3">{{ $entry->description }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $num((float) $entry->hours) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-gray-500">Nog geen uren geboekt.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 font-bold">
                        <td colspan="3" class="py-2">Totaal uren</td>
                        <td class="py-2 text-right tabular-nums">{{ $num($statement->hours()) }}</td>
                    </tr>
                </tfoot>
            </table>

            @if ($regie)
                @if ($statement->materials->isNotEmpty())
                    <h2 class="mt-8 text-sm font-bold">Materiaal</h2>
                    <table class="mt-2 w-full border-collapse text-sm">
                        <tbody>
                            @foreach ($statement->materials as $material)
                                <tr class="border-b border-gray-200">
                                    <td class="py-2 pr-3">{{ $material->description }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $num((float) $material->quantity) }} {{ $material->unit }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $eur((float) $material->price) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $eur($material->total()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <dl class="mt-6 ml-auto max-w-sm space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">
                            Arbeid, {{ $num($statement->units($statement->hours())) }} {{ $statement->perDay() ? 'dagen' : 'uur' }} tegen {{ $eur($statement->rate()) }}
                        </dt>
                        <dd class="tabular-nums">{{ $eur($statement->labour($statement->hours())) }}</dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-gray-600">Materiaal</dt><dd class="tabular-nums">{{ $eur($statement->materialsTotal()) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-300 pt-1 font-bold"><dt>Totaal excl. btw</dt><dd class="tabular-nums">{{ $eur($statement->total()) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Al gefactureerd</dt><dd class="tabular-nums">{{ $eur($statement->invoiced()) }}</dd></div>
                    <div class="flex justify-between font-bold"><dt>Nog te factureren</dt><dd class="tabular-nums">{{ $eur($statement->toInvoice()) }}</dd></div>
                </dl>
            @else
                <p class="mt-6 text-sm text-gray-600">Aangenomen werk: deze uren staan er ter informatie, de prijs is vast.</p>
            @endif

            <div class="mt-10 grid grid-cols-2 gap-8 text-sm">
                <div>
                    <p class="text-gray-500">Afgetekend namens {{ Setting::get('bedrijf.naam') }}</p>
                    @if ($statement->isSigned() && ! $statement->needsResign())
                        <p class="mt-6 border-t border-gray-400 pt-2 font-semibold">
                            {{ $project->hoursSigner?->name }}, {{ $project->hours_signed_at->translatedFormat('j F Y') }},
                            op {{ $num((float) $project->hours_signed_total) }} uur
                        </p>
                    @else
                        <p class="mt-14 border-t border-gray-400 pt-2 text-gray-500">Naam, datum en handtekening</p>
                    @endif
                </div>
                <div>
                    <p class="text-gray-500">Voor akkoord opdrachtgever</p>
                    <p class="mt-14 border-t border-gray-400 pt-2 text-gray-500">Naam, datum en handtekening</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
