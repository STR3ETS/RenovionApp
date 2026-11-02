{{-- Foto schermvullend bekijken: tikken op elke [data-photo] opent dit scherm (app.js).
     Vegen = volgende foto, knijpen of dubbeltikken = zoomen, omlaag vegen = sluiten. --}}
<div x-data="photoViewer" x-show="isOpen" x-cloak x-transition.opacity
     @photo-open.window="show($event.detail)"
     @renovion-back.window="if (isOpen) { close(); $event.preventDefault(); }"
     @keydown.escape.window="close()"
     @keydown.arrow-left.window="isOpen && go(-1)"
     @keydown.arrow-right.window="isOpen && go(1)"
     class="fixed inset-0 z-[60] flex flex-col bg-black text-white" role="dialog" aria-modal="true" aria-label="Foto bekijken">

    <div class="flex items-center justify-between px-2 pt-[calc(0.5rem_+_env(safe-area-inset-top))] pb-2">
        <span class="px-2 text-sm font-semibold text-gray-300" x-text="items.length > 1 ? (index + 1) + ' van ' + items.length : ''"></span>
        <button type="button" @click="close()" class="flex h-11 w-11 items-center justify-center rounded-full text-white transition hover:bg-white/10 active:bg-white/10" title="Sluiten">
            <x-icon name="x-mark" class="h-6 w-6" />
        </button>
    </div>

    <div class="relative min-h-0 flex-1 touch-none overflow-hidden select-none"
         @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up($event)" @pointercancel="cancel()">
        <img :src="item.src" :alt="item.caption || 'Foto'" draggable="false" class="h-full w-full object-contain" :style="{ transform }">

        {{-- Met een muis zijn er knoppen; op touch veeg je. --}}
        <button type="button" x-show="index > 0" @pointerdown.stop @click="go(-1)" class="absolute top-1/2 left-3 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20 pointer-fine:flex" title="Vorige foto">
            <x-icon name="chevron-right" class="h-5 w-5 rotate-180" />
        </button>
        <button type="button" x-show="index < items.length - 1" @pointerdown.stop @click="go(1)" class="absolute top-1/2 right-3 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20 pointer-fine:flex" title="Volgende foto">
            <x-icon name="chevron-right" class="h-5 w-5" />
        </button>
    </div>

    <div class="px-4 pt-3 pb-[calc(1rem_+_env(safe-area-inset-bottom))]">
        <p class="text-sm font-semibold" x-show="item.caption" x-text="item.caption"></p>
        <p class="mt-0.5 text-xs text-gray-300" x-show="item.meta" x-text="item.meta"></p>
    </div>
</div>
