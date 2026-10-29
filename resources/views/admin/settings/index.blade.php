<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
            Settings
        </h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
            Every configuration the records and the plotter are filed under, in one place.
        </p>
    </header>

    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        @php
            $sections = [
                [
                    'route' => 'admin.record-types.index',
                    'label' => 'Record types',
                    'description' => 'Record types are the categories a record can be filed under.',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.569 3z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h.008v.008H6V6z" />',
                ],
                [
                    'route' => 'admin.owner-types.index',
                    'label' => 'Owner types',
                    'description' => 'Owner types group the records of a single kind of owner.',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
                ],
                [
                    'route' => 'admin.locations.index',
                    'label' => 'Locations',
                    'description' => 'Locations are the places a record can be filed at.',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />',
                ],
                [
                    'route' => 'admin.stations.index',
                    'label' => 'Stations',
                    'description' => 'Stations are the DAR offices that handle records.',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />',
                ],
                [
                    'route' => 'admin.import.index',
                    'label' => 'Import',
                    'description' => 'Load a backup .sql file into this database.',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5 7.5 12M12 7.5V21" />',
                ],
            ];
        @endphp

        @foreach ($sections as $section)
            <a
                href="{{ route($section['route']) }}"
                wire:navigate
                class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition duration-200 hover:border-brand-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-brand-500"
            >
                <span class="flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:group-hover:bg-brand-500/20">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            {!! $section['icon'] !!}
                        </svg>
                    </span>

                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $section['label'] }}
                    </h2>
                </span>

                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ $section['description'] }}
                </p>

                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-brand-600 group-hover:underline dark:text-brand-300">
                    Manage<span aria-hidden="true"> &rarr;</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
