@props(['color' => 'amber'])

@php
    $dotClass = match ($color) {
        'red' => 'bg-red-500',
        'amber' => 'bg-amber-500',
        'green' => 'bg-green-500',
        'steel' => 'bg-steel-500',
        default => 'bg-gray-400',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-block h-2 w-2 shrink-0 rounded-full '.$dotClass]) }}></span>
