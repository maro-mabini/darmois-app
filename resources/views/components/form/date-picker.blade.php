@props([
    'name',
    'months' => [],
    'minYear',
    'maxYear',
    'label' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => 'Choose a date',
])

@php
    $now = now();
@endphp

{{--
    A day picked inside a month of a year.

    Three views live in one panel: the days of a month, the twelve months, and a
    decade of years. The header is the way between them -- the month label opens
    the months, the year label opens the years -- so reaching 1893 is three
    clicks rather than ninety arrow presses.

    The panel expands in place rather than floating over the form: this field
    lives at the bottom of a modal, and a floating panel would be clipped by the
    dialog's scrolling body. Expanding keeps it inside the dialog, reachable by
    keyboard, and readable on a phone.

    The browsing position -- which day of which month of which year is on screen --
    is Alpine's, because it is a view concern and no request is made for it. The
    selection is not: it is read back from the Livewire property with an x-effect,
    so opening the form for a different file shows that file's date rather than
    whatever the last one browsed to.
--}}
<x-form.field
    :label="$label ?? 'Date'"
    :name="$name"
    :required="$required"
    :hint="$hint"
>
    <div
        x-data="{
            months: @js($months),
            weekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            minYear: {{ $minYear }},
            maxYear: {{ $maxYear }},
            thisDay: {{ (int) $now->day }},
            thisMonth: {{ (int) $now->month }},
            thisYear: {{ (int) $now->year }},
            name: @js($name),

            open: false,
            view: 'days',
            browseMonth: {{ (int) $now->month }},
            browseYear: {{ (int) $now->year }},
            selected: '',
            display: '',

            /*
             * Mirror the server's value into the browsed position. Run from an
             * x-effect, so it also fires when a file is loaded into the form and
             * the property changes under the panel.
             */
            adopt(value) {
                this.selected = value || '';

                if (! this.selected) {
                    this.display = '';

                    return;
                }

                const [ year, month, day ] = String(this.selected).split('-').map(Number);

                if (! year || ! month || ! day) {
                    this.display = '';

                    return;
                }

                this.display = this.label(month, day, year);
                this.browseYear = this.clampYear(year);
                this.browseMonth = month;
            },

            label(month, day, year) {
                return this.months[month] ? this.months[month] + ' ' + day + ', ' + year : '';
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

            headerLabel() {
                if (this.view === 'days') {
                    return this.monthLabel();
                }

                if (this.view === 'months') {
                    return String(this.browseYear);
                }

                return this.decadeLabel();
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
             * The calendar is built from the browsed year and month rather than
             * from a Date parsed out of the stored string: a Y-m-d string is read
             * as UTC by Date, which puts the day off by one behind UTC. Arithmetic
             * on the components keeps the grid on the date the server holds.
             */
            daysInMonth() {
                return new Date(this.browseYear, this.browseMonth, 0).getDate();
            },

            firstWeekday() {
                return new Date(this.browseYear, this.browseMonth - 1, 1).getDay();
            },

            days() {
                return Array.from({ length: this.daysInMonth() }, (value, offset) => offset + 1);
            },

            leading() {
                return Array.from({ length: this.firstWeekday() }, (value, offset) => offset);
            },

            iso(day) {
                const month = String(this.browseMonth).padStart(2, '0');
                const date = String(day).padStart(2, '0');

                return this.browseYear + '-' + month + '-' + date;
            },

            isSelected(day) {
                return this.selected === this.iso(day);
            },

            isToday(day) {
                return this.browseYear === this.thisYear
                    && this.browseMonth === this.thisMonth
                    && day === this.thisDay;
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
             * Arrow direction follows the view: a month at a time in the calendar,
             * a year at a time among the months, a decade at a time among the years.
             */
            step(delta) {
                if (this.view === 'years') {
                    this.browseYear = this.clampYear(this.decadeStart(this.browseYear) + delta * 10);

                    return;
                }

                if (this.view === 'months') {
                    this.browseYear = this.clampYear(this.browseYear + delta);

                    return;
                }

                this.stepMonth(delta);
            },

            /*
             * The guard is written against what the step lands on, not against
             * the browsed value: the last decade is a partial one, and asking
             * whether the browsed year plus ten is still in range would leave
             * those last years unreachable.
             */
            canStep(delta) {
                if (this.view === 'years') {
                    const target = this.decadeStart(this.browseYear) + delta * 10;

                    return target >= this.minYear && target <= this.maxYear;
                }

                if (this.view === 'months') {
                    const target = this.browseYear + delta;

                    return target >= this.minYear && target <= this.maxYear;
                }

                const index = (this.browseYear - this.minYear) * 12 + (this.browseMonth - 1) + delta;

                return index >= 0 && index <= (this.maxYear - this.minYear) * 12 + 11;
            },

            stepView() {
                this.view = this.view === 'days' ? 'months' : (this.view === 'months' ? 'years' : 'months');
            },

            pickYear(year) {
                this.browseYear = year;
                this.view = 'months';
            },

            pickMonth(month) {
                this.browseMonth = month;
                this.view = 'days';
            },

            /*
             * $set is deferred. Nothing on the server needs to hear about a date
             * until the form is submitted, and the x-effect reads the new value
             * straight back out of the reactive copy.
             */
            pick(day) {
                this.$wire.$set(this.name, this.iso(day), false);
                this.open = false;
            },

            clear() {
                this.$wire.$set(this.name, '', false);
                this.open = false;
            },

            toggle() {
                this.open = ! this.open;
                this.view = 'days';

                /*
                 * The field sits at the bottom of the modal's scrolling body, so
                 * the panel opens mostly below the fold. Pull it back into view
                 * without moving the page when it already fits.
                 */
                if (this.open) {
                    this.$nextTick(() => this.$refs.panel.scrollIntoView({ block: 'nearest' }));
                }
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
        x-effect="adopt($wire.{{ $name }})"
        x-on:keydown.escape="onEscape($event)"
    >
        <button
            type="button"
            id="{{ $name }}"
            aria-haspopup="true"
            aria-controls="{{ $name }}-picker"
            :aria-expanded="open"
            x-on:click="toggle()"
            class="flex w-full items-center justify-between gap-2 rounded-lg border-0 bg-white px-3 py-2 text-left text-sm shadow-xs ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-brand-600 focus:ring-inset dark:bg-white/5 dark:ring-gray-700 dark:focus:ring-brand-500"
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
            x-ref="panel"
            id="{{ $name }}-picker"
            role="group"
            aria-label="{{ $label ?? 'Date' }} picker"
            x-show="open"
            x-cloak
            class="mt-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-950"
        >
            <div class="flex items-center justify-between gap-1">
                <button
                    type="button"
                    x-on:click="step(-1)"
                    :disabled="! canStep(-1)"
                    class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white"
                >
                    <span class="sr-only">Previous</span>
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                {{--
                    The one control that swaps what the panel below is showing:
                    the days of the month, the twelve months, or the ten years.
                    Each view opens the one above it, so a year is picked and the
                    months it holds are one click away.
                --}}
                <button
                    type="button"
                    x-on:click="stepView()"
                    class="flex items-center gap-1 rounded-md px-2 py-1.5 text-sm font-semibold text-gray-900 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:text-white dark:hover:bg-white/5"
                >
                    <span x-text="headerLabel()"></span>

                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                </button>

                <button
                    type="button"
                    x-on:click="step(1)"
                    :disabled="! canStep(1)"
                    class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white"
                >
                    <span class="sr-only">Next</span>
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>

            <div x-show="view === 'days'" class="mt-3">
                <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-500 dark:text-gray-300">
                    <template x-for="weekday in weekdays" :key="weekday">
                        <span x-text="weekday"></span>
                    </template>
                </div>

                <div class="mt-1 grid grid-cols-7 gap-1">
                    {{-- Empty cells so the first day of the month falls under its weekday. --}}
                    <template x-for="blank in leading()" :key="'blank-' + blank">
                        <span></span>
                    </template>

                    <template x-for="day in days()" :key="day">
                        <button
                            type="button"
                            x-on:click="pick(day)"
                            x-text="day"
                            :aria-current="isSelected(day) ? 'date' : null"
                            :class="isSelected(day)
                                ? 'bg-brand-600 text-white'
                                : (isToday(day)
                                    ? 'text-brand-600 ring-1 ring-brand-600 ring-inset dark:text-brand-300 dark:ring-brand-400'
                                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10')"
                            {{--
                                A fixed height rather than aspect-square: the row
                                holding the leading blanks has no other content,
                                so an aspect-ratio height resolves after the row
                                is already sized and that first row comes up short.
                            --}}
                            class="h-10 rounded-lg text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        ></button>
                    </template>
                </div>
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
                        :class="Number($wire.{{ $name }}?.split('-')[1]) === Number(number)
                            ? 'bg-brand-600 text-white'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10'"
                        class="rounded-lg px-2 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                    ></button>
                </template>
            </div>

            <div x-show="view === 'years'" class="mt-3 grid grid-cols-5 gap-1">
                <template x-for="year in years()" :key="year">
                    <button
                        type="button"
                        x-on:click="pickYear(year)"
                        x-text="year"
                        :class="Number($wire.{{ $name }}?.split('-')[0]) === year
                            ? 'bg-brand-600 text-white'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10'"
                        class="rounded-lg px-2 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                    ></button>
                </template>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800">
                <button
                    type="button"
                    x-on:click="clear()"
                    x-show="display"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:text-gray-300 dark:hover:text-white"
                >
                    Clear
                </button>

                <p x-show="! display" class="text-xs text-gray-500 dark:text-gray-300">
                    Pick a day, then a month and a year if you need to go back further.
                </p>
            </div>
        </div>
    </div>
</x-form.field>
