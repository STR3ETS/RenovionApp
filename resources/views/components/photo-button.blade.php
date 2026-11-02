{{-- Grote cameraknop in plaats van het native bestandsveld. Verwacht "files: []" in de x-data van het formulier. --}}
<label class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600 active:scale-[0.99]">
    <x-icon name="camera" class="h-5 w-5" />
    <span x-text="files.length ? 'Andere foto\'s kiezen' : 'Foto maken'">Foto maken</span>
    <input type="file" name="photos[]" multiple required accept="image/*" capture="environment" class="sr-only"
           aria-label="Foto maken of kiezen" @change="files = Array.from($event.target.files).map((file) => file.name)">
</label>
<p x-show="files.length" x-cloak class="flex items-center gap-1.5 text-xs font-medium text-green-700">
    <x-icon name="check" class="h-3.5 w-3.5" />
    <span x-text="files.length === 1 ? '1 foto gekozen: ' + files[0] : files.length + ' foto\'s gekozen'"></span>
</p>
