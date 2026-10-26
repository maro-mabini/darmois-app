{{--
    Success notifications, rendered once by the layout.

    Alpine owns the queue rather than Livewire, because a toast is transient UI
    state: the server only decides *what happened*, and this decides how long it
    stays on screen. No round trip is involved.

    Fired by any action that finished:

        $this->dispatch('toast', message: 'Record created.')

    The queue is mirrored into sessionStorage because one action navigates.
    Deleting a record from its own page redirects to the index, which replaces
    this entire layout — a notification held only in memory would be thrown away
    by that navigation before anything could read it. Writing the queue out means
    the next layout picks it back up on init and starts counting down again.
--}}
<div
    x-data="{
        items: [],
        limit: 3,
        ttl: 5000,
        nextId: 0,
        key: 'darmois:toasts',

        init() {
            this.restore();
        },

        add(message) {
            const id = ++this.nextId;

            this.items.push({ id, message });

            // Keep the newest: an older notification is the least useful one to
            // still be on screen.
            this.items = this.items.slice(-this.limit);
            this.persist();
            setTimeout(() => this.dismiss(id), this.ttl);
        },

        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
            this.persist();
        },

        persist() {
            if (this.items.length === 0) {
                sessionStorage.removeItem(this.key);

                return;
            }

            sessionStorage.setItem(this.key, JSON.stringify(this.items));
        },

        restore() {
            let stored = null;

            try {
                stored = JSON.parse(sessionStorage.getItem(this.key) ?? '[]');
            } catch {
                // Anything unreadable is dropped rather than allowed to break
                // the page; a missing notification is a smaller problem.
                sessionStorage.removeItem(this.key);

                return;
            }

            if (!Array.isArray(stored) || stored.length === 0) {
                return;
            }

            this.items = stored;
            this.nextId = this.items.reduce((highest, item) => Math.max(highest, item.id), 0);

            this.items.forEach((item) => setTimeout(() => this.dismiss(item.id), this.ttl));
        },
    }"
    x-on:toast.window="if ($event.detail?.message) add($event.detail.message)"
    aria-live="polite"
    aria-label="Notifications"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[600] flex flex-col items-center gap-2 p-4 sm:inset-x-auto sm:right-0 sm:items-end"
>
    <template x-for="item in items" :key="item.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-lg sm:w-auto dark:border-gray-700 dark:bg-gray-900"
        >
            <svg
                class="mt-0.5 size-5 shrink-0 text-green-600 dark:text-green-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                />
            </svg>

            {{-- x-text, not x-html: the message is server-supplied. --}}
            <p class="flex-1 text-sm font-medium text-gray-900 dark:text-white" x-text="item.message"></p>

            <button
                type="button"
                x-on:click="dismiss(item.id)"
                class="-mt-1 -mr-1 shrink-0 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
            >
                <span class="sr-only">Dismiss notification</span>
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
