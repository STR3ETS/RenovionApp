@props(['label', 'value', 'sub' => null, 'icon' => null, 'href' => null, 'alert' => false])

<a href="{{ $href ?? '#' }}"
   {{ $attributes->merge(['class' => 'block rounded-2xl border border-gray-200 bg-white p-4 transition '.($href ? 'hover:border-brand-400 hover:shadow-sm' : 'pointer-events-none')]) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="text-xs font-semibold text-gray-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500">
                <x-icon :name="$icon" class="h-4 w-4" />
            </span>
        @endif
    </div>
    <p class="mt-1 text-2xl font-bold text-navy-900 lg:text-3xl">{{ $value }}</p>
    @if ($sub)
        <p class="mt-0.5 text-xs font-medium {{ $alert ? 'text-brand-600' : 'text-gray-400' }}">{{ $sub }}</p>
    @endif
</a>
