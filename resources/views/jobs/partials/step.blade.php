@php
    /** @var \App\Models\JobStep $step */
    $done = $step->isDone();
    $needsPhoto = $step->needsPhoto();
@endphp

<div x-data="{ open: {{ $isOpen && ! $done ? 'true' : 'false' }} }"
     class="overflow-hidden rounded-xl border bg-surface-800 transition {{ $done ? 'border-surface-700 opacity-70' : 'border-surface-700' }}">

    <button @click="open = !open" class="flex w-full items-start gap-3 p-4 text-left">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $done ? 'bg-green-500/20 text-green-300' : 'bg-surface-700 text-slate-300' }}">
            @if ($done)
                <x-icon name="check" class="h-4 w-4" />
            @else
                {{ $step->position + 1 }}
            @endif
        </span>

        <span class="min-w-0 flex-1">
            <b class="block text-sm font-semibold {{ $done ? 'text-slate-400 line-through' : 'text-white' }}">{{ $step->title }}</b>
            @if ($step->hint)
                <small class="mt-0.5 block text-xs text-slate-500">{{ $step->hint }}</small>
            @endif
        </span>

        @if ($step->photo_required || $step->photos->isNotEmpty())
            <span class="flex shrink-0 items-center gap-1 rounded-full px-2 py-1 text-[11px] font-bold {{ $step->photos->isNotEmpty() ? 'bg-green-500/15 text-green-300' : 'bg-amber-500/15 text-amber-300' }}"
                  title="{{ $step->photo_required ? 'Foto verplicht bij deze stap' : 'Foto gemaakt' }}">
                <x-icon name="camera" class="h-3.5 w-3.5" />
                {{ $step->photos->count() ?: '' }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak class="border-t border-surface-700 px-4 py-3">
        @if ($step->photos->isNotEmpty())
            <div class="mb-3 flex gap-2 overflow-x-auto">
                @foreach ($step->photos as $foto)
                    <img src="{{ route('photos.show', $foto) }}" alt="" class="h-20 w-20 shrink-0 rounded-lg object-cover">
                @endforeach
            </div>
        @endif

        @if ($step->note)
            <p class="mb-3 rounded-lg bg-surface-850 p-3 text-xs text-slate-300">{{ $step->note }}</p>
        @endif

        @if ($canExecute && $job->status->isActive())
            <div class="flex flex-wrap gap-2">
                {{-- Camera direct openen op de telefoon --}}
                <form method="POST" action="{{ route('job-steps.photos', [$job, $step]) }}" enctype="multipart/form-data" class="contents">
                    @csrf
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ $needsPhoto ? 'bg-brand-500 text-white hover:bg-brand-600' : 'border border-surface-600 text-slate-200 hover:text-white' }}">
                        <x-icon name="camera" class="h-4 w-4" /> Foto maken
                        <input type="file" name="photos[]" accept="image/*" capture="environment" multiple hidden
                               onchange="this.form.submit()">
                    </label>
                </form>

                <div x-data="{ note: false }" class="contents">
                    <button @click="note = !note" class="flex items-center gap-1.5 rounded-xl border border-surface-600 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:text-white">
                        <x-icon name="pencil" class="h-4 w-4" /> Notitie
                    </button>

                    <form x-show="note" x-cloak method="POST" action="{{ route('job-steps.note', [$job, $step]) }}" class="mt-2 w-full">
                        @csrf
                        @method('PATCH')
                        <textarea name="note" rows="2" placeholder="Wat is er bijzonder aan deze stap?"
                                  class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">{{ $step->note }}</textarea>
                        <button type="submit" class="mt-2 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white">Opslaan</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('job-steps.toggle', [$job, $step]) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="flex items-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-bold transition {{ $done ? 'border border-surface-600 text-slate-300 hover:text-white' : 'bg-brand-500 text-white hover:bg-brand-600' }}">
                        @unless ($done)
                            <x-icon name="check" class="h-4 w-4" />
                        @endunless
                        {{ $done ? 'Ongedaan maken' : 'Stap klaar' }}
                    </button>
                </form>
            </div>

            @if ($needsPhoto)
                <p class="mt-2 text-xs font-semibold text-amber-300">Bij deze stap hoort een foto voordat je hem kunt afvinken.</p>
            @endif
        @endif
    </div>
</div>
