{{-- Het offertedocument (briefing §6): rendert de builder-blokken zoals de klant ze ziet.
     Hoofdstukken zijn genummerd; de nummering telt alleen blokken met een titel
     en slaat de kop, de brief, foto's en lijnen over. --}}
@php
    use App\Support\QuoteBlockRegistry;

    $documentBlocks = QuoteBlockRegistry::ensureTyped($quote->blocks, $quote);
@endphp

<div class="quote-doc @container space-y-4">
    @foreach ($documentBlocks as $block)
        @continue(! view()->exists('quotes.blocks.'.$block['type']))
        @php
            // Een blok zonder inhoud rendert niets en hoort dan ook geen nummer te krijgen.
            $html = trim(view('quotes.blocks.'.$block['type'], ['mode' => 'view', 'data' => $block['data'] ?? [], 'quote' => $quote])->render());
        @endphp
        @if ($html !== '')
            <div @class(['quote-chapter' => QuoteBlockRegistry::isNumbered($block['type'])])>{!! $html !!}</div>
        @endif
    @endforeach
</div>
