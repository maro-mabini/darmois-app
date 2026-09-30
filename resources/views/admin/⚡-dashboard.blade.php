<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
        This is Dashboard page in the Admin Workspace.
    </h1>

    <a
        href="{{ route('admin.records.index') }}"
        wire:navigate
        class="mt-6 inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
    >
        View records
    </a>
</div>
