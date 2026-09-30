@props([
    'id',
    'title',
    'message',
    'action',
    'errorKey' => null,
    'confirmLabel' => 'Delete',
])

{{--
    A destructive-action confirmation built on the native <dialog> element.

    Opened with the id of the row being confirmed, so no server round trip is
    needed to fill it in:

        $dispatch('modal-open', { id: 'owner-type-delete', payload: { id: 1, label: 'ARB' } })

    The dialog deliberately does not close itself on click. The server closes it
    by dispatching 'modal-saved' once the delete actually succeeded, so a refused
    delete (an owner type still in use, say) leaves the dialog open with the
    reason on screen.
--}}
<dialog
    x-data="{ pending: null, label: '', busy: false }"
    x-ref="dialog"
    x-on:modal-open.window="if ($event.detail?.id === @js($id)) { pending = $event.detail.payload.id; label = $event.detail.payload.label; busy = false; if (! $refs.dialog.open) $refs.dialog.showModal() }"
    x-on:modal-saved.window="if ($event.detail === @js($id)) $refs.dialog.close()"
    x-on:close="busy = false; $dispatch('modal-closed', @js($id))"
    x-on:click="if ($event.target === $refs.dialog) $refs.dialog.close()"
    aria-labelledby="{{ $id }}-title"
    class="m-auto w-[calc(100%-2rem)] max-w-md open:flex open:flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white p-0 text-gray-900 shadow-xl backdrop:bg-gray-900/50 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
>
    <div class="flex gap-4 px-6 py-5">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-500/10">
            <svg
                class="size-5 text-red-600 dark:text-red-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
                />
            </svg>
        </div>

        <div class="min-w-0">
            <h2 id="{{ $id }}-title" class="text-base font-semibold">
                {{ $title }}
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="font-medium text-gray-900 dark:text-white" x-text="label"></span>
                {{ $message }}
            </p>
        </div>
    </div>

    @error($errorKey)
        <p class="mx-6 mb-5 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
            {{ $message }}
        </p>
    @enderror

    <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-950">
        <button
            type="button"
            x-on:click="$refs.dialog.close()"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
        >
            Cancel
        </button>

        <button
            type="button"
            x-on:click="if (busy) return; busy = true; $wire.{{ $action }}(pending).then(() => (busy = false))"
            x-bind:disabled="busy"
            x-bind:class="busy && 'cursor-wait opacity-70'"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500"
        >
            <span x-show="! busy">{{ $confirmLabel }}</span>
            <span x-show="busy" x-cloak>Working&hellip;</span>
        </button>
    </div>
</dialog>
