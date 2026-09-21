@props(['label', 'value', 'href' => null, 'alert' => false])

@php
    $classes = 'block rounded-xl border bg-white p-3 transition '.($alert && $value > 0
        ? 'border-brand-300 ring-1 ring-brand-200'
        : 'border-gray-200');
@endphp

<a href="{{ $href ?? '#' }}" {{ $attributes->merge(['class' => $classes.($href ? ' hover:border-brand-400' : ' pointer-events-none')]) }}>
    <p class="text-2xl font-bold {{ $alert && $value > 0 ? 'text-brand-600' : 'text-navy-900' }}">{{ $value }}</p>
    <p class="mt-0.5 text-xs font-medium text-gray-500">{{ $label }}</p>
</a>
