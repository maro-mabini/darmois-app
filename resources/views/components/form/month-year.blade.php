@props([
    'name',
    'months',
    'years',
    'label' => null,
    'required' => false,
    'hint' => null,
    'yearPlaceholder' => 'Year',
    'monthPlaceholder' => 'Month',
])

@php
    // Two properties rather than one combined value, so each half can be
    // validated and so a half-finished choice is detectable.
    $yearName = "{$name}_year";
    $monthName = "{$name}_month";
@endphp

{{--
    A month and a year chosen from dropdowns.

    A month on its own is not a value the `date` column can hold, so the day is
    implied: the first of the month. A date filed this way is therefore precise
    to the month, and the stored day is always 01.

    The label is pointed at the month select, which is the first control in the
    DOM; the year's own select is picked up by the grid order and the shared
    error list below the pair.
--}}
<x-form.field
    :label="$label ?? 'Date'"
    :name="$monthName"
    :required="$required"
    :hint="$hint ?? 'Only the month and year are recorded.'"
    :error-keys="[$yearName]"
>
    <div class="grid grid-cols-2 gap-3">
        <x-form.select
            :name="$monthName"
            :options="$months"
            :placeholder="$monthPlaceholder"
        />

        <x-form.select
            :name="$yearName"
            :options="$years"
            :placeholder="$yearPlaceholder"
        />
    </div>
</x-form.field>
