@props([])

<img src="{{ asset('nova.jpg') }}" alt="Nova" {{ $attributes->merge(['class' => 'h-10 w-10 shrink-0 rounded-full object-cover']) }}>
