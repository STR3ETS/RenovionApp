@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

@if ($isEdit)
    <section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
        <div x-rich="block.data.title" @blur="save()" data-ph="Titel" class="mb-3 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.body" @blur="save()" data-ph="Schrijf hier de tekst…" class="quote-prose text-sm leading-relaxed text-gray-600"></div>
    </section>
@elseif (filled($d['body'] ?? '') || filled($d['title'] ?? ''))
    <section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
        @if (filled($d['title'] ?? ''))
            <h2 class="mb-3 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title']) !!}</h2>
        @endif
        <div class="quote-prose text-sm leading-relaxed text-gray-600">{!! QuoteText::render($d['body'] ?? '') !!}</div>
    </section>
@endif
