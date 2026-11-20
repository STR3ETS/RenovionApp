{{-- Wat binnen 31 dagen verloopt: één keer per dag als venster, voor wie het moet regelen.
     Het venster onthoudt per browser dat het vandaag al getoond is. --}}
@php($expiringItems = $expiringItems ?? collect())
@if ($expiringItems->isNotEmpty())
    <div x-data="{
            open: false,
            key: 'verloop-melding',
            init() {
                let seen = null;
                try { seen = localStorage.getItem(this.key); } catch (e) {}
                this.open = seen !== @js(today()->toDateString());
            },
            close() {
                try { localStorage.setItem(this.key, @js(today()->toDateString())); } catch (e) {}
                this.open = false;
            },
         }"
         x-show="open" x-cloak @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-4"
         role="dialog" aria-modal="true" aria-label="Wat binnenkort verloopt">
        <div class="motion-pop max-h-[85vh] w-full overflow-y-auto rounded-t-2xl border border-surface-700 bg-surface-800 p-5 shadow-2xl shadow-black/50 sm:max-w-lg sm:rounded-2xl" @click.outside="close()">
            <div class="mb-1 flex items-start justify-between gap-3">
                <h2 class="text-base font-bold text-ink">Verloopt <span class="text-brand-500">binnenkort</span></h2>
                <button type="button" @click="close()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-surface-600 text-ink2 hover:text-ink" aria-label="Sluiten">
                    <x-icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
            <p class="mb-4 text-sm text-muted">
                {{ $expiringItems->count() }} {{ $expiringItems->count() === 1 ? 'stuk verloopt' : 'stukken verlopen' }} binnen 31 dagen of {{ $expiringItems->count() === 1 ? 'is' : 'zijn' }} al verlopen.
                Zonder geldig stuk kan iemand niet op de bouw worden ingepland.
            </p>

            <div class="space-y-2">
                @foreach ($expiringItems->take(8) as $item)
                    <a href="{{ $item['url'] }}" class="flex items-center gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3 transition hover:border-surface-600">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $item['severity'] === 'rood' ? 'bg-red-500' : 'bg-amber-400' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $item['title'] }}</span>
                            <span class="block truncate text-xs text-muted">{{ $item['label'] }}, {{ $item['subtitle'] }}</span>
                        </span>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-faint" />
                    </a>
                @endforeach
            </div>

            <div class="mt-4 flex gap-2">
                <a href="{{ route('attention.index') }}" class="flex-1 rounded-full bg-brand-500 py-2.5 text-center text-sm font-bold text-white transition hover:bg-brand-600">Naar de inbox</a>
                <button type="button" @click="close()" class="btn">Later</button>
            </div>
        </div>
    </div>
@endif
