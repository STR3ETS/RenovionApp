{{-- Voet van het offertedocument: op de klantpagina (quotes.public) en in de app (Api\App\QuoteController@document). --}}
<div class="px-6 py-4 text-center text-xs text-gray-500">
    <p class="font-semibold text-gray-500">Renovion - geen half werk en vage beloftes</p>
    <p class="mt-1">Mercatorweg 28, 6827 DC Arnhem, info@renovion.nl, +31 6 395 353 00, KVK 95384782</p>
    @if ($links ?? true)
        <p class="mt-1 print:hidden"><a href="{{ route('privacy') }}" class="tap font-semibold text-gray-600 underline underline-offset-2">Privacyverklaring</a></p>
    @endif
</div>
