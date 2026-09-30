@props(['title' => null])

{{--
    Shell for every page component. The page content arrives as $slot; this file
    owns the document head, the navigation, and the light/dark canvas.

    Two independent pieces of state:
      collapsed - desktop rail vs full sidebar, persisted so a reload keeps it
      open      - the mobile drawer, deliberately not persisted
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="{
        collapsed: localStorage.getItem('darmois:sidebar-collapsed') === '1',
        open: false,
        init() {
            this.$watch('collapsed', (value) => {
                localStorage.setItem('darmois:sidebar-collapsed', value ? '1' : '0');
            });
        },
    }"
    class="h-full bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100"
>
    @php
        $navigation = [
            [
                'route' => 'admin.dashboard',
                'pattern' => 'admin.dashboard',
                'label' => 'Dashboard',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />',
            ],
            [
                'route' => 'admin.records.index',
                'pattern' => 'admin.records.*',
                'label' => 'Records',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />',
            ],
            [
                'route' => 'admin.record-types.index',
                'pattern' => 'admin.record-types.*',
                'label' => 'Record types',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.569 3z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h.008v.008H6V6z" />',
            ],
            [
                'route' => 'admin.owner-types.index',
                'pattern' => 'admin.owner-types.*',
                'label' => 'Owner types',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
            ],
        ];
    @endphp

    <div class="min-h-full lg:flex">
        {{-- Mobile bar. The sidebar is a drawer below lg, so it needs a way in. --}}
        <header
            class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 dark:border-gray-800 dark:bg-gray-900 lg:hidden"
        >
            <button
                type="button"
                x-on:click="open = true"
                class="-ml-1 rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                aria-controls="app-sidebar"
                aria-label="Open navigation"
            >
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <span class="text-sm font-semibold tracking-tight">{{ config('app.name') }}</span>
        </header>

        {{-- Scrim, mobile only. Closes the drawer on tap. --}}
        <div
            x-show="open"
            x-on:click="open = false"
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
            aria-hidden="true"
        ></div>

        {{--
            The sidebar itself. On mobile it is a fixed drawer translated off
            canvas; from lg it is a static, full-height column whose width is
            driven by `collapsed`.
        --}}
        <aside
            id="app-sidebar"
            :class="[
                open ? 'translate-x-0' : '-translate-x-full',
                collapsed ? 'lg:w-20' : 'lg:w-64',
            ]"
            class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white transition-[width,transform] duration-300 ease-in-out group/sidebar dark:border-gray-800 dark:bg-gray-900 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
        >
            {{--
                `relative` so the collapse toggle can be positioned against the
                header's edges. Horizontal padding lives on the children, not
                here, so `left-full` lands exactly on the sidebar border.
            --}}
            <div class="relative flex h-16 items-center border-b border-gray-200 dark:border-gray-800">
                <a
                    href="{{ route('admin.dashboard') }}"
                    wire:navigate
                    :class="collapsed ? 'justify-center' : ''"
                    class="mx-3 flex min-w-0 flex-1 items-center gap-2.5 rounded-md py-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                >
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </span>

                    <span
                        x-show="! collapsed"
                        x-cloak
                        class="truncate text-sm font-semibold tracking-tight"
                    >{{ config('app.name') }}</span>
                </a>

                {{--
                    Collapse control. It floats on the sidebar's right edge
                    (left-full + -translate-x-1/2 straddles the border) and only
                    appears on hover. When collapsed it re-centres over the brand
                    mark instead, which it then fully covers.

                    pointer-events-none is required, not cosmetic: a 40px
                    invisible button sitting over the logo would otherwise
                    swallow clicks meant for the brand link. focus-visible brings
                    it back so the control is still reachable by keyboard.
                --}}
                <button
                    type="button"
                    x-on:click="collapsed = ! collapsed"
                    :class="collapsed ? 'left-1/2' : 'left-full'"
                    class="absolute top-1/2 z-[500] hidden size-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 opacity-0 shadow-sm transition-all duration-300 pointer-events-none hover:bg-gray-100 hover:text-gray-900 focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 group-hover/sidebar:pointer-events-auto group-hover/sidebar:opacity-100 lg:flex dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                    :aria-expanded="! collapsed"
                    aria-controls="app-sidebar"
                >
                    <span class="sr-only">Toggle sidebar</span>

                    {{--
                        Heroicons' paired entry/exit arrows rather than one glyph
                        that spins: the rectangle in each icon is the sidebar, so
                        the arrow already points the way the panel is about to go.
                        Both groups are cloaked, otherwise the wrong arrow would
                        paint before Alpine restores the persisted state.
                    --}}
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        {{-- Expanded: this collapses the sidebar. --}}
                        <g x-show="! collapsed" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                        </g>

                        {{-- Collapsed: this brings the sidebar back. --}}
                        <g x-show="collapsed" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                        </g>
                    </svg>
                </button>

                {{-- Mobile has no rail to collapse, so it just dismisses. --}}
                <button
                    type="button"
                    x-on:click="open = false"
                    class="mr-3 shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 lg:hidden dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                >
                    <span class="sr-only">Close navigation</span>
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Pages bring their own `max-w-7xl px-4 py-10`, so <main> stays bare. --}}
            <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Main">
                @foreach ($navigation as $item)
                    @php
                        $active = request()->routeIs($item['pattern']);
                    @endphp

                    <a
                        href="{{ route($item['route']) }}"
                        wire:navigate
                        @class([
                            'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $active,
                            'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => ! $active,
                        ])
                        :aria-current="{{ $active ? 'page' : 'false' }}"
                        :title="collapsed ? @js($item['label']) : null"
                    >
                        <span class="flex size-5 shrink-0 items-center justify-center">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                {!! $item['icon'] !!}
                            </svg>
                        </span>

                        <span x-show="! collapsed" x-cloak class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            {{ $slot }}
        </main>
    </div>

    {{--
        Outside the flex wrapper so a notification is never clipped by the
        sidebar's overflow, and inside <body> so it outlives a page navigation.
    --}}
    <x-toast />
</body>
</html>
