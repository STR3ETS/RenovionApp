{{-- De drie weergaven van de planning: week, maand en capaciteit. --}}
<nav class="flex gap-1 rounded-xl bg-surface-850 p-1">
    @foreach (['week' => 'Week', 'maand' => 'Maand', 'capaciteit' => 'Capaciteit'] as $key => $label)
        <a href="{{ route('planning.index', ['weergave' => $key === 'week' ? null : $key]) }}"
           class="shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold transition {{ $view === $key ? 'bg-surface-700 text-white' : 'text-slate-400 hover:text-slate-200' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
