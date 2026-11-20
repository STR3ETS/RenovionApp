@props(['wide' => true])

{{-- De KPI-strip uit het prototype: vier kaarten, waarvan de eerste anderhalf
     keer zo breed. Die eerste is het getal waar het die week om draait. De
     volgorde bepaalt de kleur van de lijn bovenaan en van het label. --}}
<section data-motion-fan {{ $attributes->merge(['class' => 'kpis mb-[22px]'.($wide ? '' : ' even')]) }}>
    {{ $slot }}
</section>
