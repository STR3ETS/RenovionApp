@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

@if ($isEdit)
    <figure class="rounded-2xl border border-gray-200 bg-white p-4">
        <template x-if="block.data.url">
            <div class="group/img relative">
                <img :src="block.data.url" class="max-h-[480px] w-full rounded-xl bg-gray-50 object-contain" alt="">
                <button type="button" @click="block.data.url = ''; save()"
                        class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-red-500 opacity-0 shadow-sm transition group-hover/img:opacity-100 hover:bg-red-50" title="Foto verwijderen">
                    <x-icon name="trash" class="h-3.5 w-3.5" />
                </button>
            </div>
        </template>
        <template x-if="!block.data.url">
            <label class="flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/50 p-10 text-center transition hover:border-brand-400"
                   @dragover.prevent="$el.classList.add('border-brand-400', 'bg-brand-50')"
                   @dragleave.prevent="$el.classList.remove('border-brand-400', 'bg-brand-50')"
                   @drop.prevent="$el.classList.remove('border-brand-400', 'bg-brand-50'); upload($event.dataTransfer.files[0]).then(url => { if (url) { block.data.url = url; save(); } })">
                <input type="file" accept="image/*" class="hidden"
                       @change="upload($event.target.files[0]).then(url => { if (url) { block.data.url = url; save(); } $event.target.value = ''; })">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-500"><x-icon name="camera" class="h-5 w-5" /></span>
                <span class="text-sm font-bold text-navy-900" x-text="uploading ? 'Uploaden…' : 'Klik of sleep een foto hierheen'"></span>
                <span class="text-xs text-gray-400">JPG, PNG of WebP · max 10 MB</span>
            </label>
        </template>
        <div x-rich="block.data.caption" @blur="save()" data-ph="Bijschrift (optioneel)" class="mt-3 text-center text-xs text-gray-400"></div>
    </figure>
@elseif (filled($d['url'] ?? ''))
    <figure class="rounded-2xl border border-gray-200 bg-white p-4">
        <img src="{{ $d['url'] }}" alt="{{ \App\Support\QuoteText::plain($d['caption'] ?? 'Foto') }}" loading="lazy" class="max-h-[480px] w-full rounded-xl bg-gray-50 object-contain">
        @if (filled($d['caption'] ?? ''))
            <figcaption class="mt-3 text-center text-xs text-gray-400">{!! QuoteText::render($d['caption']) !!}</figcaption>
        @endif
    </figure>
@endif
