@props([
    'name',
    'rows' => 4,
    'required' => false,
    'placeholder' => null,
])

<textarea
    id="{{ $name }}"
    name="{{ $name }}"
    rows="{{ $rows }}"
    @if ($required) required @endif
    @if ($placeholder) placeholder="{{ $placeholder }}" @endif
    @error($name) aria-invalid="true" @enderror
    wire:model="{{ $name }}"
    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-600 focus:ring-inset dark:bg-white/5 dark:text-white dark:ring-gray-700 dark:placeholder:text-gray-300 dark:focus:ring-indigo-500"
></textarea>
