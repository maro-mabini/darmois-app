@props([
    'name',
    'months' => [],
    'minYear',
    'maxYear',
    'label' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => 'Choose a month and year',
])

@php
    /*
     * Two Livewire properties rather than one combined value. The picker always
     * sets them together, but the server still has to treat each half on its
     * own: a month with no year is not a date, and it is the server that says so.
     */
    $yearName = "{$name}_year";
    $monthName = "{$name}_month";

    $now = now();
@endphp

{{--
    A month and a year picked inside one panel, with no day.

    The panel expands in place rather than floating over the form: this field
    lives at the bottom of a modal, and a floating panel would be clipped by the
    dialog's scrolling body. Expanding keeps it inside the dialog, reachable by
    keyboard, and readable on a phone.

    The browsing position -- which month of which year is on screen -- is
    Alpine's, because it is a view concern and no request is made for it. The
    selection is not: it is read back from the Livewire properties with an
    x-effect, so opening the form for a different file shows that file's month
    rather than whatever the last one browsed to.
--}}
<x-form.field
    :label="$label ?? 'Date'"
    :name="$monthName"
    :required="$required"
    :hint="$hint ?? 'Only the month and year are recorded.'"
    :error-keys="[$yearName]"
>
    <div
        x-data="{
            months: @js($months),
            minYear: {{ $minYear }},
            maxYear: {{ $maxYear }},
            thisMonth: {{ (int) $now->month }},
            thisYear: {{ (int) $now->year }},
            monthName: @js($monthName),
            yearName: @js($yearName),

            open: false,
            view: 'months',
            browseMonth: {{ (int) $now->month }},
            browseYear: {{ (int) $now->year }},
            display: '',

            /*
             * Mirror the server's pair into the browsed position. Run from an
             * x-effect, so it also fires when a file is loaded into the form
             * and the properties change under the panel.
             */
            adopt(month, year) {
                this.display = this.label(month, year);
                this.browseMonth = Number(month) || this.thisMonth;
                this.browseYear = this.clampYear(Number(year) || this.thisYear);
            },

            label(month, year) {
                return this.months[month] ? this.months[month] + ' ' + year : '';
            },

            monthLabel() {
                return this.months[this.browseMonth] + ' ' + this.browseYear;
            },

            decadeLabel() {
                const years = this.years();

                if (years.length === 0) {
                    return '';
                }

                return years.length === 1
                    ? String(years[0])
                    : years[0] + '–' + years[years.length - 1];
            },

            clampYear(year) {
                return Math.min(Math.max(year, this.minYear), this.maxYear);
            },

            /*
             * The years are derived from the browsed year rather than held
             * alongside it, so the decade on screen and the year the months
             * belong to cannot drift apart.
             */
            decadeStart(year) {
                return this.minYear + Math.floor((year - this.minYear) / 10) * 10;
            },

            years() {
                const start = this.decadeStart(this.browseYear);

                return Array.from({ length: 10 }, (value, offset) => start + offset)
                    .filter((year) => year >= this.minYear && year <= this.maxYear);
            },

            /*
             * Months are counted off a flat index rather than by adding days to a
             * date, so stepping from December to January crosses the year
             * without a Date, a timezone, or a short month getting in the way.
             */
            stepMonth(delta) {
                const index = Math.min(
                    Math.max((this.browseYear - this.minYear) * 12 + (this.browseMonth - 1) + delta, 0),
                    (this.maxYear - this.minYear) * 12 + 11,
                );

                this.browseYear = this.minYear + Math.floor(index / 12);
                this.browseMonth = (index % 12) + 1;
            },

            /*
             * Stepping moves by whole decades, and the guard is written against
             * the decade rather than the browsed year: the last decade is a
             * partial one, and asking whether the browsed year plus ten is
             * still in range would leave those last years unreachable.
             */
            stepDecade(delta) {
                this.browseYear = this.clampYear(this.decadeStart(this.browseYear) + delta * 10);
            },

            canStepDecade(delta) {
                const target = this.decadeStart(this.browseYear) + delta * 10;

                return target >= this.minYear && target <= this.maxYear;
            },

            pickYear(year) {
                this.browseYear = year;
                this.view = 'months';
            },

            /*
             * The year is written first: it is the half that is always present
             * once a month has been chosen, so the pair never lands on the
             * server as a month with nothing under it.
             *
             * $set is deferred. Nothing on the server needs to hear about a
             * month until the form is submitted, and the x-effect reads the new
             * value straight back out of the reactive copy.
             */
            pickMonth(month) {
                this.$wire.$set(this.yearName, String(this.browseYear), false);
                this.$wire.$set(this.monthName, String(month), false);
                this.open = false;
            },

            clear() {
                this.$wire.$set(this.monthName, '', false);
                this.$wire.$set(this.yearName, '', false);
                this.open = false;
            },

            toggle() {
                this.open = ! this.open;
                this.view = 'months';
            },

            /*
             * Escape closes the panel, and only the panel. The key is left
             * alone when there is nothing of ours open, so it still reaches the
             * dialog and closes the form the way it did before this field
             * existed.
             */
            onEscape(event) {
                if (! this.open) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                this.open = false;
            },
        }"
        x-effect="adopt($wire.{{ $monthName }}, $wire.{{ $yearName }})"
        x-on:keydown.escape="onEscape($event)"
    >
        <button
            type="button"
            id="{{ $monthName }}"
            aria-haspopup="true"
            aria-controls="{{ $monthName }}-picker"
            :aria-expanded="open"
            x-on:click="toggle()"
            class="flex w-full items-center justify-between gap-2 rounded-lg border-0 bg-white px-3 py-2 text-left text-sm shadow-xs ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-indigo-600 focus:ring-inset dark:bg-white/5 dark:ring-gray-700 dark:focus:ring-indigo-500"
        >
            <span
                x-text="display || @js($placeholder)"
                :class="display ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'"
                class="truncate"
            ></span>

            <svg
                class="size-5 shrink-0 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
        </button>

        <div
            id="{{ $monthName }}-picker"
            role="group"
            aria-label="{{ $label ?? 'Date' }} picker"
            x-show="open"
            x-cloak
            class="mt-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-950"
        >
            <div class="flex items-center justify-between gap-1">
                <button
                    type="button"
                    x-on:click="view === 'months' ? stepMonth(-1) : stepDecade(-1)"
                    :disabled="view === 'years' && ! canStepDecade(-1)"
                    class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white"
                >
                    <span class="sr-only">Previous</span>
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                {{--
                    The one control that swaps what the panel below is showing:
                    the twelve months, or the ten years. Both live in the same
                    panel, so a year is picked and the months it holds are one
                    click away.
                --}}
                <button
                    type="button"
                    x-on:click="view = view === 'months' ? 'years' : 'months'"
                    class="flex items-center gap-1 rounded-md px-2 py-1.5 text-sm font-semibold text-gray-900 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-white dark:hover:bg-white/5"
                >
                    <span x-text="view === 'months' ? monthLabel() : decadeLabel()"></span>

                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                </button>

                <button
                    type="button"
                    x-on:click="view === 'months' ? stepMonth(1) : stepDecade(1)"
                    :disabled="view === 'years' && ! canStepDecade(1)"
                    class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white"
                >
                    <span class="sr-only">Next</span>
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>

            {{--
                Month keys arrive from the server as strings, so both the
                comparison and the pick go through Number: '4' === 4 is false,
                and the month would never show itself as chosen.
            --}}
            <div x-show="view === 'months'" class="mt-3 grid grid-cols-3 gap-1">
                <template x-for="(month, number) in months" :key="number">
                    <button
                        type="button"
                        x-on:click="pickMonth(Number(number))"
                        x-text="month"
                        :class="Number($wire.{{ $monthName }}) === Number(number)
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10'"
                        class="rounded-lg px-2 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    ></button>
                </template>
            </div>

            <div x-show="view === 'years'" class="mt-3 grid grid-cols-5 gap-1">
                <template x-for="year in years()" :key="year">
                    <button
                        type="button"
                        x-on:click="pickYear(year)"
                        x-text="year"
                        :class="Number($wire.{{ $yearName }}) === year
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10'"
                        class="rounded-lg px-2 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    ></button>
                </template>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800">
                <button
                    type="button"
                    x-on:click="clear()"
                    x-show="display"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-300 dark:hover:text-white"
                >
                    Clear
                </button>

                <p x-show="! display" class="text-xs text-gray-500 dark:text-gray-300">
                    Pick a month and a year.
                </p>
            </div>
        </div>
    </div>
</x-form.field>
