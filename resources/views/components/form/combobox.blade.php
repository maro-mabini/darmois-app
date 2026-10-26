@props([
    'name',
    'options' => [],
    'placeholder' => 'Select an option',
    'searchPlaceholder' => 'Search…',
    'live' => false,
])

{{--
    A searchable select.

    The open list, the typed filter and the highlighted row are browser state,
    so they live in Alpine. The chosen id is Livewire state: it is entangled
    with the component property named by `name`, which means selecting an
    option sends it with the next request exactly like a `wire:model` select,
    and a server-side change (loading a record, failing validation) flows back
    into the field.
--}}
<div
    x-data="{
        value: $wire.$entangle(@js($name), @js($live)),
        open: false,
        query: '',
        active: 0,
        all: @js($options),
        filtered() {
            const term = this.query.trim().toLowerCase();

            const options = Object.entries(this.all)
                .map(([id, label]) => ({ id: String(id), label: String(label) }))
                .filter((option) => term === '' || option.label.toLowerCase().includes(term));

            // The blank choice is a real option, exactly as it was on the
            // select this replaced: without it a field that has a value could
            // never be cleared again. It is only offered while searching is
            // idle, so it does not sit in the way of a filtered list.
            return term === '' ? [{ id: '', label: @js($placeholder) }, ...options] : options;
        },
        selectedLabel() {
            return this.all[this.value] ?? @js($placeholder);
        },
        hasValue() {
            return this.value !== null && this.value !== '';
        },
        flipUp: false,
        listMaxHeight: '224px',
        // The combobox also lives inside modals, whose scrolling body clips
        // anything painted past its edge. Measure that container so the panel
        // can open upwards and cap its list when the trigger sits at the
        // bottom of the form.
        scrollParent() {
            let parent = this.$el.parentElement;

            while (parent && parent !== document.body) {
                const overflowY = getComputedStyle(parent).overflowY;

                if (overflowY === 'auto' || overflowY === 'scroll') {
                    return parent;
                }

                parent = parent.parentElement;
            }

            return null;
        },
        positionList() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const parent = this.scrollParent();
            const bounds = parent
                ? parent.getBoundingClientRect()
                : { top: 0, bottom: window.innerHeight };

            const spaceAbove = rect.top - bounds.top;
            const spaceBelow = bounds.bottom - rect.bottom;
            const flipUp = spaceBelow < 280 && spaceAbove > spaceBelow;

            // The search row, the list padding and the panel border take about
            // this much room before the first option.
            const chrome = 52;
            const room = (flipUp ? spaceAbove : spaceBelow) - chrome - 8;

            this.flipUp = flipUp;
            this.listMaxHeight = `${Math.max(120, Math.min(224, Math.round(room)))}px`;
        },
        show() {
            this.query = '';
            this.positionList();
            this.open = true;

            const options = this.filtered();
            const index = options.findIndex((option) => option.id === String(this.value));
            this.active = index === -1 ? 0 : index;

            this.$nextTick(() => {
                // preventScroll keeps focus inside the panel from scrolling a
                // modal body to the input, which would move the trigger out
                // from under the fixed panel.
                this.$refs.search.focus({ preventScroll: true });
                this.scrollActiveIntoView();
            });
        },
        close(refocus = false) {
            this.open = false;

            if (refocus) {
                this.$nextTick(() => this.$refs.trigger.focus());
            }
        },
        choose(id) {
            this.value = String(id);
            this.close(true);
        },
        move(step) {
            if (! this.open) {
                this.show();
                return;
            }

            const count = this.filtered().length;

            if (count === 0) {
                return;
            }

            this.active = (this.active + step + count) % count;
            this.scrollActiveIntoView();
        },
        selectActive() {
            const option = this.filtered()[this.active];

            if (option) {
                this.choose(option.id);
            }
        },
        scrollActiveIntoView() {
            this.$nextTick(() => {
                const list = this.$refs.list;
                const option = list?.querySelector('[data-active=\'true\']');

                if (!list || !option) {
                    return;
                }

                // Scroll only the list. scrollIntoView() would also scroll the
                // modal body, which moves the trigger away from the panel.
                const listRect = list.getBoundingClientRect();
                const optionRect = option.getBoundingClientRect();

                if (optionRect.top < listRect.top) {
                    list.scrollTop -= listRect.top - optionRect.top;
                } else if (optionRect.bottom > listRect.bottom) {
                    list.scrollTop += optionRect.bottom - listRect.bottom;
                }
            });
        },
    }"
    class="relative"
    x-on:keydown.arrow-down.prevent="move(1)"
    x-on:keydown.arrow-up.prevent="move(-1)"
    x-on:keydown.enter.prevent="open ? selectActive() : show()"
    x-on:keydown.escape.prevent="open ? close(true) : null"
    x-on:keydown.tab="close()"
    x-on:mousedown.outside="close()"
    x-on:click.outside="close()"
    x-on:modal-open.window="close()"
    x-on:scroll.window.capture="if (open) positionList()"
    x-on:resize.window="if (open) positionList()"
>
    <button
        type="button"
        id="{{ $name }}"
        x-ref="trigger"
        x-on:click="open ? close() : show()"
        aria-haspopup="listbox"
        aria-controls="{{ $name }}-listbox"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        @error($name) aria-invalid="true" @enderror
        class="flex w-full items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-left text-sm shadow-xs ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-brand-600 focus:outline-hidden dark:bg-white/5 dark:ring-gray-700 dark:focus:ring-brand-500"
    >
        <span
            x-text="selectedLabel()"
            x-bind:class="hasValue() ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-300'"
            class="min-w-0 truncate"
        ></span>

        <svg
            class="size-4 shrink-0 text-gray-400 transition-transform"
            x-bind:class="open && 'rotate-180'"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        x-bind:class="flipUp ? 'bottom-full mb-1 origin-bottom' : 'top-full mt-1 origin-top'"
        class="absolute z-30 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="flex items-center gap-2 border-b border-gray-100 px-3 dark:border-gray-800">
            <svg class="size-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>

            <input
                type="search"
                x-ref="search"
                x-model="query"
                x-on:input="active = 0"
                role="combobox"
                aria-controls="{{ $name }}-listbox"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                x-bind:aria-activedescendant="'{{ $name }}-option-' + active"
                aria-autocomplete="list"
                aria-label="{{ $searchPlaceholder }}"
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
                class="w-full border-0 bg-transparent py-2 text-sm text-gray-900 placeholder-gray-500 focus:ring-0 focus:outline-hidden dark:text-white dark:placeholder-gray-300 [&::-webkit-search-cancel-button]:appearance-none"
            >
        </div>

        <ul
            id="{{ $name }}-listbox"
            role="listbox"
            x-ref="list"
            x-bind:style="{ maxHeight: listMaxHeight }"
            class="overflow-y-auto p-1"
        >
            <template x-for="(option, index) in filtered()" :key="option.id">
                <li
                    role="option"
                    x-bind:id="'{{ $name }}-option-' + index"
                    x-bind:aria-selected="String(value) === option.id ? 'true' : 'false'"
                    x-bind:data-active="index === active ? 'true' : 'false'"
                    x-on:click="choose(option.id)"
                    x-on:mouseenter="active = index"
                    x-bind:class="index === active
                        ? 'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-white'
                        : 'text-gray-700 dark:text-gray-200'"
                    class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-3 py-2 text-sm"
                >
                    <span x-text="option.label"></span>

                    <svg
                        x-show="String(value) === option.id"
                        class="size-4 shrink-0 text-brand-600 dark:text-brand-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </li>
            </template>

            <li x-show="filtered().length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-300">
                No matches.
            </li>
        </ul>
    </div>
</div>
