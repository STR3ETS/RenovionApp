@props([
    'href',
    'active' => false,
    // Een teller naast het label. Null laat hem weg, 0 blijft staan zodat je
    // ziet dat een filter bestaat maar leeg is.
    'count' => null,
])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
   {{ $attributes->merge(['class' => 'flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition '.($active ? 'bg-brand-500 text-white' : 'text-slate-400 hover:text-white')]) }}>
    {{ $slot }}
    @if ($count !== null)
        <span class="rounded-full px-1.5 text-[10px] font-bold {{ $active ? 'bg-white/25 text-white' : 'bg-surface-700 text-slate-400' }}">{{ $count }}</span>
    @endif
</a>
