@props(['web', 'app'])

{{-- Knoptekst: de website houdt "+ Project", de iOS/Android-app krijgt de tekst uit het prototype met een plusicoon. --}}
@nativeapp
    <x-icon name="plus" class="mr-1.5 h-4 w-4" />{{ $app }}
@else
    {{ $web }}
@endnativeapp
