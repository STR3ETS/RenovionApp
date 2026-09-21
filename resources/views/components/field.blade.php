@props(['label', 'name', 'error' => null])

<div {{ $attributes->merge(['class' => '']) }}>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
    {{ $slot }}
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
