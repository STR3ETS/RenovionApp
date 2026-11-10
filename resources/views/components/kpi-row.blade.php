@props(['wide' => true])

{{-- De KPI-strip uit het prototype: vier kaarten, waarvan de eerste anderhalf
     keer zo breed. Die eerste is het getal waar het die week om draait. --}}
<section data-motion-fan
         {{ $attributes->merge(['class' => 'mb-5 grid grid-cols-2 gap-3 '.($wide ? 'lg:grid-cols-[1.5fr_repeat(3,minmax(0,1fr))]' : 'lg:grid-cols-4')]) }}>
    {{ $slot }}
</section>
