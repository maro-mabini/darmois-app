@props([
    'label',
    'name',
    'required' => false,
    'hint' => null,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
        @if ($required)
            <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>

    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1 text-xs text-gray-500 dark:text-gray-300">{{ $hint }}</p>
    @endif

    <div class="mt-1">
        {{ $slot }}
    </div>

    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
