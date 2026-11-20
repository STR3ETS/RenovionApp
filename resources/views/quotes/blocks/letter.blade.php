@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

{{-- De persoonlijke brief: aanhef, tekst, wie tekent, de handtekening en de slogan --}}
@if ($isEdit)
    <section class="rounded-2xl border border-gray-200 bg-white p-6 @2xl:p-8">
        <div x-rich="block.data.greeting" @blur="save()" data-ph="Beste mevrouw of meneer," class="mb-3 text-base font-semibold text-navy-950"></div>
        <div x-rich="block.data.body" @blur="save()" data-ph="Schrijf hier de persoonlijke brief…" class="quote-prose text-sm leading-relaxed text-gray-600"></div>
        <div x-rich="block.data.closing" @blur="save()" data-ph="Met vriendelijke groet," class="mt-5 text-sm text-gray-600"></div>

        <div class="mt-3">
            <template x-if="block.data.signature">
                <div class="group/sig relative inline-block">
                    <img :src="block.data.signature" class="h-16 w-auto" alt="Handtekening">
                    <button type="button" @click="block.data.signature = ''; save()"
                            class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full border border-gray-200 bg-white text-red-500 opacity-0 shadow-sm transition group-hover/sig:opacity-100" title="Handtekening verwijderen">
                        <x-icon name="x-mark" class="h-3 w-3" />
                    </button>
                </div>
            </template>
            <template x-if="!block.data.signature">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-gray-300 px-4 py-3 text-xs font-semibold text-gray-500 transition hover:border-brand-400 hover:text-brand-600">
                    <input type="file" accept="image/*" class="hidden"
                           @change="upload($event.target.files[0]).then(url => { if (url) { block.data.signature = url; save(); } $event.target.value = ''; })">
                    <x-icon name="pencil-square" class="h-4 w-4" />
                    <span x-text="uploading ? 'Uploaden…' : 'Handtekening toevoegen (foto of scan)'"></span>
                </label>
            </template>
        </div>

        <div x-rich="block.data.signer" @blur="save()" data-ph="Naam van wie tekent" class="mt-2 text-sm font-bold text-navy-950"></div>
        <div x-rich="block.data.role" @blur="save()" data-ph="Functie" class="text-xs text-gray-400"></div>

        <div x-rich="block.data.slogan" @blur="save()" data-ph="Slogan" class="mt-6 border-t border-gray-200 pt-4 text-center text-sm font-semibold text-brand-600 italic"></div>
    </section>
@elseif (filled($d['body'] ?? ''))
    <section class="rounded-2xl border border-gray-200 bg-white p-6 @2xl:p-8">
        @if (filled($d['greeting'] ?? ''))
            <p class="mb-3 text-base font-semibold text-navy-950">{!! QuoteText::render($d['greeting']) !!}</p>
        @endif
        <div class="quote-prose text-sm leading-relaxed text-gray-600">{!! QuoteText::render($d['body']) !!}</div>
        @if (filled($d['closing'] ?? ''))
            <p class="mt-5 text-sm text-gray-600">{!! QuoteText::render($d['closing']) !!}</p>
        @endif
        @if (filled($d['signature'] ?? ''))
            <img src="{{ $d['signature'] }}" alt="Handtekening van {{ QuoteText::plain($d['signer'] ?? '') }}" class="mt-3 h-16 w-auto">
        @endif
        @if (filled($d['signer'] ?? ''))
            <p class="mt-2 text-sm font-bold text-navy-950">{!! QuoteText::render($d['signer']) !!}</p>
        @endif
        @if (filled($d['role'] ?? ''))
            <p class="text-xs text-gray-400">{!! QuoteText::render($d['role']) !!}</p>
        @endif
        @if (filled($d['slogan'] ?? ''))
            <p class="mt-6 border-t border-gray-200 pt-4 text-center text-sm font-semibold text-brand-600 italic">{!! QuoteText::render($d['slogan']) !!}</p>
        @endif
    </section>
@endif
