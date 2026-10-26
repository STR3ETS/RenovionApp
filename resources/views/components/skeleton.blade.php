@props(['lines' => 3, 'avatar' => false, 'dark' => false])

{{-- Skeleton loader (stijlgids §08): shimmer in de vorm van de content die komt. --}}
<div {{ $attributes->merge(['class' => 'w-full']) }} role="status" aria-label="Laden">
    <div class="flex items-start gap-3">
        @if ($avatar)
            <span class="skeleton h-10 w-10 shrink-0 rounded-full {{ $dark ? 'skeleton-dark' : '' }}"></span>
        @endif
        <div class="min-w-0 flex-1 space-y-2">
            @for ($i = 0; $i < $lines; $i++)
                <span class="skeleton block h-3 {{ $dark ? 'skeleton-dark' : '' }} {{ $i === $lines - 1 && $lines > 1 ? 'w-2/3' : 'w-full' }}"></span>
            @endfor
        </div>
    </div>
</div>
