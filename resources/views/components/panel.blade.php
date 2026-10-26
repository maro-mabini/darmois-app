@props(['title' => null, 'description' => null, 'padded' => true])

{{--
    The white surface every dashboard block sits on. Headers are optional so
    the same component also wraps a plain table, which is why padding is a prop
    rather than baked in: a table wants its rows flush with the border.
--}}
<section {{ $attributes->class(['rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900']) }}>
    @if ($title !== null || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-800">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white">
                    {{ $title }}
                </h2>

                @if ($description !== null)
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-300">
                        {{ $description }}
                    </p>
                @endif
            </div>

            @isset($actions)
                <div class="shrink-0 text-sm">
                    {{ $actions }}
                </div>
            @endisset
        </header>
    @endif

    <div class="{{ $padded ? 'p-4 sm:p-6' : '' }}">
        {{ $slot }}
    </div>
</section>
