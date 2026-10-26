@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

@if ($isEdit)
    <section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
        <div x-rich="block.data.title" @blur="save()" data-ph="Randvoorwaarden & scope" class="mb-3 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.text" @blur="save()" data-ph="Voorwaarden en scope…" class="quote-prose text-sm leading-relaxed text-gray-500"></div>
    </section>
@elseif (filled($d['text'] ?? ''))
    <section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
        <h2 class="mb-3 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Randvoorwaarden & scope') !!}</h2>
        <div class="quote-prose text-sm leading-relaxed text-gray-500">{!! QuoteText::render($d['text']) !!}</div>
    </section>
@endif
