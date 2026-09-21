@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center']) }}>
    <p class="text-sm font-semibold text-gray-600">{{ $title }}</p>
    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-400">{{ $subtitle }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
