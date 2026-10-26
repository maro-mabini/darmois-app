@props([
    'name',
    'type' => 'text',
    'required' => false,
    'placeholder' => null,
    'autocomplete' => null,
])

<input
    type="{{ $type }}"
    id="{{ $name }}"
    name="{{ $name }}"
    @if ($required) required @endif
    @if ($placeholder) placeholder="{{ $placeholder }}" @endif
    @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
    @error($name) aria-invalid="true" @enderror
    wire:model="{{ $name }}"
    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600 focus:ring-inset disabled:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-gray-700 dark:placeholder:text-gray-300 dark:focus:ring-brand-500"
/>
