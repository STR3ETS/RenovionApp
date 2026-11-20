{{-- Een regel uit een raamovereenkomst is een vaste verkoopprijs: laat zien wat er overblijft --}}
@php($lineMargin = $line->frameworkMarginPct())
@if ($lineMargin !== null)
    <span class="ml-1 rounded-full px-1.5 py-0.5 font-bold {{ \App\Support\Margin::badgeClasses($lineMargin) }}">marge {{ \App\Support\Margin::format($lineMargin) }}</span>
@endif
