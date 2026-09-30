@props([
    'name',
    'options' => [],
    'placeholder' => null,
    'required' => false,
])

<select
    id="{{ $name }}"
    name="{{ $name }}"
    @if ($required) required @endif
    @error($name) aria-invalid="true" @enderror
    wire:model="{{ $name }}"
    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-indigo-600 focus:ring-inset dark:bg-white/5 dark:text-white dark:ring-gray-700 dark:focus:ring-indigo-500"
>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
    @endforeach
</select>
