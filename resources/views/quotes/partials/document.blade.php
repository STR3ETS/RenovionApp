{{-- Het offertedocument (briefing §6): rendert de builder-blokken zoals de klant ze ziet. --}}
@php
    $documentBlocks = \App\Support\QuoteBlockRegistry::ensureTyped($quote->blocks, $quote);
@endphp

<div class="space-y-4">
    @foreach ($documentBlocks as $block)
        @includeIf('quotes.blocks.'.$block['type'], ['mode' => 'view', 'data' => $block['data'] ?? [], 'quote' => $quote])
    @endforeach
</div>
