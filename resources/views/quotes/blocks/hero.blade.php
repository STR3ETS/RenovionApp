@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<header class="overflow-hidden rounded-2xl bg-navy-950 p-6 text-white lg:p-8">
    <div class="mb-6 flex items-start justify-between gap-4">
        <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
        <span class="rounded-full bg-navy-900 px-3 py-1 text-xs font-semibold whitespace-nowrap text-navy-200">{{ $quote->number }}, v{{ $quote->version }}</span>
    </div>

    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Offerte" class="text-xs font-bold tracking-widest text-brand-400 uppercase"></div>
        <div x-rich="block.data.subtitle" @blur="save()" data-ph="Type werk" class="mt-1 text-2xl font-bold lg:text-3xl"></div>
    @else
        <p class="text-xs font-bold tracking-widest text-brand-400 uppercase">{!! QuoteText::render($d['title'] ?? 'Offerte') !!}</p>
        <h1 class="mt-1 text-2xl font-bold lg:text-3xl">{!! QuoteText::render($d['subtitle'] ?? 'Renovatiewerkzaamheden') !!}</h1>
    @endif
    <p class="mt-1 text-navy-100">{{ $quote->customer->name }}</p>

    <div class="mt-6 grid gap-6 border-t border-white/10 pt-6 text-sm sm:grid-cols-3">
        <div class="space-y-3">
            <div>
                <p class="text-xs font-bold text-navy-300">Offertenummer</p>
                <p class="text-navy-100">{{ $quote->number }}</p>
            </div>
            <div>
                <p class="text-xs font-bold text-navy-300">Offertedatum</p>
                <p class="text-navy-100">{{ $quote->created_at->translatedFormat('j F Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-bold text-navy-300">Geldig tot</p>
                <p class="text-navy-100">{{ $quote->valid_until?->translatedFormat('j F Y') ?? 'in overleg' }}</p>
            </div>
        </div>
        <div>
            <p class="mb-1.5 text-xs font-bold text-navy-300">Van</p>
            <p class="font-semibold text-white">Renovion</p>
            <div class="text-navy-100">
                <p>Mercatorweg 28, 6827 DC Arnhem</p>
                <p>info@renovion.nl</p>
                <p>+31 6 395 353 00</p>
                <p>KVK 95384782</p>
            </div>
        </div>
        <div>
            <p class="mb-1.5 text-xs font-bold text-navy-300">Aan</p>
            <p class="font-semibold text-white">{{ $quote->customer->name }}</p>
            <div class="text-navy-100">
                @if (filled($quote->customer->address))<p>{{ $quote->customer->address }}</p>@endif
                @if (filled($quote->customer->postal_code) || filled($quote->customer->city))<p>{{ trim($quote->customer->postal_code.' '.$quote->customer->city) }}</p>@endif
                @if (filled($quote->customer->email))<p>{{ $quote->customer->email }}</p>@endif
                @if (filled($quote->customer->phone))<p>{{ $quote->customer->phone }}</p>@endif
            </div>
        </div>
    </div>
</header>
