@props([
    'id',
    'title',
    'description' => null,
])

{{--
    A modal built on the native <dialog> element.

    showModal() puts it in the top layer, which is why this is not a positioned
    div: the sidebar's collapse toggle sits at z-500, and a fixed overlay would
    have to out-rank it. The browser also gives us the focus trap, the Escape
    key, and the inert background for free.

    Opened by dispatching a window event rather than by shared Alpine state, so
    a trigger and this component do not have to live in the same x-data scope:

        $dispatch('modal-open', 'owner-type-edit')
--}}
<dialog
    x-ref="dialog"
    x-on:modal-open.window="if ($event.detail === @js($id) && ! $refs.dialog.open) $refs.dialog.showModal()"
    x-on:modal-saved.window="if ($event.detail === @js($id)) $refs.dialog.close()"
    x-on:close="$dispatch('modal-closed', @js($id))"
    aria-labelledby="{{ $id }}-title"
    class="m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-lg open:flex open:flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white p-0 text-gray-900 shadow-xl backdrop:bg-gray-900/50 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
>
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
        <div>
            <h2 id="{{ $id }}-title" class="text-lg font-semibold">
                {{ $title }}
            </h2>

            @if ($description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                    {{ $description }}
                </p>
            @endif
        </div>

        <button
            type="button"
            x-on:click="$refs.dialog.close()"
            class="-mt-1 -mr-2 rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
        >
            <span class="sr-only">Close</span>
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="overflow-y-auto px-6 py-5">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-950">
            {{ $footer }}
        </div>
    @endif
</dialog>
