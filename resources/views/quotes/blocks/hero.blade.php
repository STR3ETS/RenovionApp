@php
    use App\Support\QuoteHeroStyle;
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
    // Achtergrond (kleur, foto, overlay) en bijpassende tekstkleuren als CSS-variabelen.
    // In de builder rekent heroResolve() in de browser hetzelfde uit, zodat je het live ziet.
    $hero = $isEdit ? null : QuoteHeroStyle::resolve($d);
@endphp

<header class="overflow-hidden rounded-2xl p-6 text-(--hero-fg) [print-color-adjust:exact] [-webkit-print-color-adjust:exact] lg:p-8"
        @if ($isEdit) :style="heroResolve(block.data).style" @else style="{{ $hero['style'] }}" @endif>
    <div class="mb-6 flex items-start justify-between gap-4">
        {{-- Het logo is wit; op een lichte achtergrond krijgt het een navy plaatje. --}}
        @if ($isEdit)
            <span :class="heroResolve(block.data).theme === 'dark' ? 'rounded-xl bg-navy-950 px-3 py-2' : ''" class="inline-flex">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
            </span>
        @else
            <span class="inline-flex {{ $hero['theme'] === 'dark' ? 'rounded-xl bg-navy-950 px-3 py-2' : '' }}">
                <img src="{{ asset('images/renovion-logo.svg') }}" alt="Renovion" class="h-9 w-auto">
            </span>
        @endif
        <span class="rounded-full bg-(--hero-chip) px-3 py-1 text-xs font-semibold whitespace-nowrap text-(--hero-fg-soft)">{{ $quote->number }}, v{{ $quote->version }}</span>
    </div>

    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Offerte" class="text-xs font-bold tracking-widest text-(--hero-accent) uppercase"></div>
        <div x-rich="block.data.subtitle" @blur="save()" data-ph="Type werk" class="mt-1 text-2xl font-bold lg:text-3xl"></div>
    @else
        <p class="text-xs font-bold tracking-widest text-(--hero-accent) uppercase">{!! QuoteText::render($d['title'] ?? 'Offerte') !!}</p>
        <h1 class="mt-1 text-2xl font-bold lg:text-3xl">{!! QuoteText::render($d['subtitle'] ?? 'Renovatiewerkzaamheden') !!}</h1>
    @endif
    <p class="mt-1 text-(--hero-fg-soft)">{{ $quote->customer->name }}</p>

    <div class="mt-6 grid gap-6 border-t border-(--hero-line) pt-6 text-sm sm:grid-cols-3">
        <div class="space-y-3">
            <div>
                <p class="text-xs font-bold text-(--hero-fg-muted)">Offertenummer</p>
                <p class="text-(--hero-fg-soft)">{{ $quote->number }}</p>
            </div>
            <div>
                <p class="text-xs font-bold text-(--hero-fg-muted)">Offertedatum</p>
                <p class="text-(--hero-fg-soft)">{{ $quote->created_at->translatedFormat('j F Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-bold text-(--hero-fg-muted)">Geldig tot</p>
                <p class="text-(--hero-fg-soft)">{{ $quote->valid_until?->translatedFormat('j F Y') ?? 'in overleg' }}</p>
            </div>
        </div>
        <div>
            <p class="mb-1.5 text-xs font-bold text-(--hero-fg-muted)">Van</p>
            <p class="font-semibold">Renovion</p>
            <div class="text-(--hero-fg-soft)">
                <p>Mercatorweg 28, 6827 DC Arnhem</p>
                <p>info@renovion.nl</p>
                <p>+31 6 395 353 00</p>
                <p>KVK 95384782</p>
            </div>
        </div>
        <div>
            <p class="mb-1.5 text-xs font-bold text-(--hero-fg-muted)">Aan</p>
            <p class="font-semibold">{{ $quote->customer->name }}</p>
            <div class="text-(--hero-fg-soft)">
                @if (filled($quote->customer->address))<p>{{ $quote->customer->address }}</p>@endif
                @if (filled($quote->customer->postal_code) || filled($quote->customer->city))<p>{{ trim($quote->customer->postal_code.' '.$quote->customer->city) }}</p>@endif
                @if (filled($quote->customer->email))<p>{{ $quote->customer->email }}</p>@endif
                @if (filled($quote->customer->phone))<p>{{ $quote->customer->phone }}</p>@endif
            </div>
        </div>
    </div>

    @if ($isEdit)
        {{-- Achtergrond instellen: kleur, foto, overlay en tekstkleur. Alleen in de builder. --}}
        <div class="mt-6 rounded-xl border border-(--hero-line) bg-(--hero-chip) p-3" @click.stop @mousedown.stop>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                <span class="text-[10px] font-bold tracking-widest text-(--hero-fg-muted) uppercase">Achtergrond</span>

                {{-- Kleur --}}
                <div class="flex items-center gap-1.5" title="Achtergrondkleur">
                    <template x-for="preset in heroPresets" :key="preset.hex">
                        <button type="button" @click="block.data.bg = preset.hex" :title="preset.label"
                                :style="'background:' + preset.hex"
                                :class="(block.data.bg || '{{ QuoteHeroStyle::DEFAULT_BG }}').toLowerCase() === preset.hex ? 'ring-2 ring-brand-500 ring-offset-1' : ''"
                                class="h-6 w-6 rounded-full border border-black/15 shadow-sm transition hover:scale-110"></button>
                    </template>
                    <label class="relative flex h-6 w-6 cursor-pointer items-center justify-center rounded-full border border-dashed border-(--hero-fg-muted) text-(--hero-fg-muted) transition hover:scale-110" title="Eigen kleur kiezen">
                        <x-icon name="plus" class="h-3 w-3" />
                        <input type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                               :value="block.data.bg || '{{ QuoteHeroStyle::DEFAULT_BG }}'" @input="block.data.bg = $event.target.value">
                    </label>
                </div>

                <span class="hidden h-4 w-px bg-(--hero-line) sm:block"></span>

                {{-- Foto --}}
                <template x-if="!block.data.image">
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-full bg-(--hero-chip) px-3 py-1.5 text-xs font-semibold text-(--hero-fg) transition hover:bg-(--hero-line)">
                        <x-icon name="camera" class="h-3.5 w-3.5" />
                        <span x-text="uploading ? 'Uploaden…' : 'Foto als achtergrond'"></span>
                        <input type="file" accept="image/*" class="hidden"
                               @change="upload($event.target.files[0]).then(url => { if (url) { block.data.image = url; if (block.data.overlay == null) block.data.overlay = {{ QuoteHeroStyle::DEFAULT_OVERLAY }}; } $event.target.value = ''; })">
                    </label>
                </template>
                <template x-if="block.data.image">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-xs font-semibold text-(--hero-fg)" title="Hoe sterk de kleur over de foto ligt">
                            Overlay
                            <input type="range" min="0" max="95" step="5" class="h-1.5 w-28 cursor-pointer accent-brand-500"
                                   :value="block.data.overlay ?? {{ QuoteHeroStyle::DEFAULT_OVERLAY }}" @input="block.data.overlay = Number($event.target.value)">
                            <span class="w-8 text-(--hero-fg-muted)" x-text="(block.data.overlay ?? {{ QuoteHeroStyle::DEFAULT_OVERLAY }}) + '%'"></span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-full bg-(--hero-chip) px-3 py-1.5 text-xs font-semibold text-(--hero-fg) transition hover:bg-(--hero-line)" title="Andere foto kiezen">
                            <x-icon name="camera" class="h-3.5 w-3.5" />
                            <span x-text="uploading ? 'Uploaden…' : 'Andere foto'"></span>
                            <input type="file" accept="image/*" class="hidden"
                                   @change="upload($event.target.files[0]).then(url => { if (url) block.data.image = url; $event.target.value = ''; })">
                        </label>
                        <button type="button" @click="block.data.image = ''" class="flex items-center gap-1 text-xs font-semibold text-(--hero-fg-muted) transition hover:text-red-400" title="Foto verwijderen">
                            <x-icon name="trash" class="h-3.5 w-3.5" /> Foto weg
                        </button>
                    </div>
                </template>

                <span class="hidden h-4 w-px bg-(--hero-line) sm:block"></span>

                {{-- Tekstkleur --}}
                <div class="flex items-center gap-2" title="Tekstkleur: automatisch op basis van de achtergrond, of zelf kiezen">
                    <span class="text-[10px] font-bold tracking-widest text-(--hero-fg-muted) uppercase">Tekst</span>
                    <div class="flex rounded-full bg-(--hero-chip) p-0.5">
                        <template x-for="option in [['auto', 'Auto'], ['light', 'Licht'], ['dark', 'Donker']]" :key="option[0]">
                            <button type="button" @click="block.data.text = option[0]"
                                    :class="(block.data.text || 'auto') === option[0] ? 'bg-brand-500 text-white' : 'text-(--hero-fg-soft) hover:text-(--hero-fg)'"
                                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition" x-text="option[1]"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    @endif
</header>
