@props(['title' => null])

{{--
    Shell for every page component. The page content arrives as $slot; this file
    owns the document head, the navigation, and the light/dark canvas.

    The shell's state, all of it client-side:
      collapsed      - desktop rail vs full sidebar, persisted so a reload keeps it
      open           - the mobile drawer, deliberately not persisted
      appearanceOpen - the floating appearance panel, expanded on demand
      theme          - 'light', 'dark', or 'system' until the user picks a side
      palette        - 'dar' (the seal's green and gold) or 'iris', also persisted
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name') }}</title>

    {{-- The agency seal, used for the tab and for a home-screen shortcut. --}}
    <link rel="icon" type="image/png" href="{{ asset('storage/dar_logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('storage/dar_logo_large.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="{
        collapsed: localStorage.getItem('darmois:sidebar-collapsed') === '1',
        open: false,
        appearanceOpen: false,
        theme: localStorage.getItem('darmois:theme') ?? 'system',
        palette: localStorage.getItem('darmois:palette') ?? 'dar',
        dark: false,
        init() {
            this.applyTheme();
            this.applyPalette();

            this.$watch('collapsed', (value) => {
                localStorage.setItem('darmois:sidebar-collapsed', value ? '1' : '0');
            });

            // With no explicit choice the OS stays in charge, including when it
            // changes while the page is open. A chosen theme is left alone.
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.theme === 'system') {
                    this.applyTheme();
                }
            });
        },
        /*
         * The class goes on <html>, not <body>, so it also reaches anything
         * painted outside the body -- the backdrop of a native dialog, for one.
         * 'system' removes both classes and leaves the decision to the
         * prefers-color-scheme rule in app.css.
         */
        applyTheme() {
            const root = document.documentElement;

            root.classList.toggle('dark', this.theme === 'dark');
            root.classList.toggle('light', this.theme === 'light');

            this.dark = this.theme === 'dark'
                || (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        },
        setTheme(theme) {
            this.theme = theme;
            localStorage.setItem('darmois:theme', theme);
            this.applyTheme();
        },
        /*
         * The palette is a second, independent choice: a data attribute on
         * <html> that re-points the `brand` and `gold` ramps in app.css. The
         * default removes the attribute instead of writing a palette name on it,
         * so a visitor who never picked one carries no palette at all.
         */
        applyPalette() {
            const root = document.documentElement;

            if (this.palette === 'dar') {
                root.removeAttribute('data-palette');
            } else {
                root.dataset.palette = this.palette;
            }
        },
        setPalette(palette) {
            this.palette = palette;
            localStorage.setItem('darmois:palette', palette);
            this.applyPalette();
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
                'route' => 'admin.plotter.index',
                'pattern' => 'admin.plotter.*',
                'label' => 'Plotter',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />',
            ],
            [
                'route' => 'admin.settings.index',
                // The configuration pages live behind Settings, so this is the
                // entry that stays current while the user is in one of them.
                'pattern' => [
                    'admin.settings.*',
                    'admin.record-types.*',
                    'admin.owner-types.*',
                    'admin.locations.*',
                    'admin.stations.*',
                    'admin.import.*',
                ],
                'label' => 'Settings',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
            ],
        ];
    @endphp

    <div class="min-h-full lg:flex">
        {{-- Mobile bar. The sidebar is a drawer below lg, so it needs a way in. --}}
        <header
            class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 print:hidden dark:border-gray-800 dark:bg-gray-900 lg:hidden"
        >
            <button
                type="button"
                x-on:click="open = true; appearanceOpen = false"
                class="-ml-1 rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                aria-controls="app-sidebar"
                aria-label="Open navigation"
            >
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <img
                src="{{ asset('storage/dar_logo_large.png') }}"
                alt=""
                width="300"
                height="300"
                class="size-8 shrink-0 object-contain p-[3px]"
            >

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
            class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white transition-[width,transform] duration-300 ease-in-out print:hidden group/sidebar dark:border-gray-800 dark:bg-gray-900 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
        >
            {{--
                `relative` so the collapse toggle can be positioned against the
                header's edges. Horizontal padding lives on the children, not
                here, so `left-full` lands exactly on the sidebar border.
            --}}
            <div class="relative flex h-16 items-center border-b-2 border-gold-400 dark:border-gold-500">
                <a
                    href="{{ route('admin.dashboard') }}"
                    wire:navigate
                    :class="collapsed ? 'justify-center' : ''"
                    aria-label="{{ config('app.name') }} home"
                    class="mx-3 flex min-w-0 flex-1 items-center gap-2.5 rounded-md py-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                >
                    {{--
                        The agency seal itself. The PNG is transparent where the
                        gold letters sit, so the mark takes on whatever the
                        sidebar is: light leaves, dark leaves. Nothing is painted
                        behind it and nothing clips its edges.
                    --}}
                    <img
                        src="{{ asset('storage/dar_logo_large.png') }}"
                        alt=""
                        width="300"
                        height="300"
                        class="size-8 shrink-0 object-contain p-[3px]"
                    >

                    <span
                        x-show="! collapsed"
                        x-cloak
                        class="whitespace-nowrap text-sm font-semibold tracking-tight"
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
                    class="absolute top-1/2 z-[500] hidden size-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 opacity-0 shadow-sm transition-all duration-300 pointer-events-none hover:bg-gray-100 hover:text-gray-900 focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 group-hover/sidebar:pointer-events-auto group-hover/sidebar:opacity-100 lg:flex dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
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
                    class="mr-3 shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 lg:hidden dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
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
                        $active = request()->routeIs(...(array) $item['pattern']);
                    @endphp

                    <a
                        href="{{ route($item['route']) }}"
                        wire:navigate
                        @class([
                            'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600',
                            'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' => $active,
                            'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => ! $active,
                        ])
                        aria-current="{{ $active ? 'page' : 'false' }}"
                        :title="collapsed ? @js($item['label']) : null"
                    >
                        {{--
                            The gold rail is the accent half of the DAR palette.
                            Decorative: the tinted row, the text color and
                            aria-current already carry the active state.
                        --}}
                        @if ($active)
                            <span class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-gold-400" aria-hidden="true"></span>
                        @endif

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
        Appearance control.

        The theme and palette used to sit at the foot of the sidebar, where they
        were only reachable after scrolling the nav and disappeared behind the
        drawer on a phone. A floating button keeps them one tap away from any
        page and any scroll position.

        It is a disclosure, not a menu: the button carries aria-expanded and
        aria-controls, the panel is toggled with Alpine, and an outside click or
        Escape closes it. Both choices stay client preferences in localStorage,
        so nothing here touches the server.

        The wrapper is hidden while the mobile drawer is open -- it sits above
        the drawer's z-index, so otherwise it would float over the scrim.
    --}}
    <div
        x-show="! open"
        x-on:click.outside="appearanceOpen = false"
        x-on:keydown.escape.window="appearanceOpen = false"
        class="fixed right-5 bottom-5 z-[550] flex flex-col items-end gap-3 print:hidden sm:right-6 sm:bottom-6"
    >
        {{--
            The panel. Stacked above the button by DOM order, and scaled from
            the bottom-right corner so it grows out of the control rather than
            appearing from nowhere.
        --}}
        <div
            id="appearance-panel"
            x-show="appearanceOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="translate-y-2 scale-95 opacity-0"
            x-transition:enter-end="translate-y-0 scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="translate-y-0 scale-100 opacity-100"
            x-transition:leave-end="translate-y-2 scale-95 opacity-0"
            class="w-72 origin-bottom-right rounded-2xl border border-gray-200 bg-white p-3 shadow-xl ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-800 dark:ring-white/10"
            role="group"
            aria-labelledby="appearance-panel-title"
        >
            <p
                id="appearance-panel-title"
                class="px-1 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300"
            >
                Appearance
            </p>

            <div class="space-y-3">
                <div>
                    <p class="mb-1.5 px-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                        Theme
                    </p>

                    <div
                        class="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-white/5"
                        role="group"
                        aria-label="Color theme"
                    >
                        <button
                            type="button"
                            x-on:click="setTheme('system')"
                            :aria-pressed="theme === 'system'"
                            :class="theme === 'system'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-600 hover:bg-white/60 dark:text-gray-300 dark:hover:bg-white/10'"
                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        >
                            System
                        </button>

                        <button
                            type="button"
                            x-on:click="setTheme('light')"
                            :aria-pressed="theme === 'light'"
                            :class="theme === 'light'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-600 hover:bg-white/60 dark:text-gray-300 dark:hover:bg-white/10'"
                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        >
                            Light
                        </button>

                        <button
                            type="button"
                            x-on:click="setTheme('dark')"
                            :aria-pressed="theme === 'dark'"
                            :class="theme === 'dark'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-600 hover:bg-white/60 dark:text-gray-300 dark:hover:bg-white/10'"
                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        >
                            Dark
                        </button>
                    </div>
                </div>

                <div>
                    <p class="mb-1.5 px-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                        Palette
                    </p>

                    <div
                        class="grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1 dark:bg-white/5"
                        role="group"
                        aria-label="Color palette"
                    >
                        <button
                            type="button"
                            x-on:click="setPalette('dar')"
                            :aria-pressed="palette === 'dar'"
                            aria-label="DAR green and gold palette"
                            title="DAR green and gold"
                            :class="palette === 'dar'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-600 hover:bg-white/60 dark:text-gray-300 dark:hover:bg-white/10'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        >
                            {{-- Literal colours, not `brand-*`: the swatch
                                 previews a palette that is not the one on. --}}
                            <span class="flex gap-1" aria-hidden="true">
                                <span class="size-3 rounded-full" style="background-color: #1b6c47"></span>
                                <span class="size-3 rounded-full" style="background-color: #f5c518"></span>
                            </span>

                            <span>DAR</span>
                        </button>

                        <button
                            type="button"
                            x-on:click="setPalette('iris')"
                            :aria-pressed="palette === 'iris'"
                            aria-label="Iris indigo and violet palette"
                            title="Iris indigo and violet"
                            :class="palette === 'iris'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-600 hover:bg-white/60 dark:text-gray-300 dark:hover:bg-white/10'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        >
                            <span class="flex gap-1" aria-hidden="true">
                                <span class="size-3 rounded-full" style="background-color: #4f46e5"></span>
                                <span class="size-3 rounded-full" style="background-color: #c084fc"></span>
                            </span>

                            <span>Iris</span>
                        </button>

                        <button
                            type="button"
                            x-on:click="setPalette('ai2x')"
                            :aria-pressed="palette === 'ai2x'"
                            aria-label="Ai2x green and white palette"
                            title="Ai2x green and white"
                            :class="palette === 'ai2x'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-600 hover:bg-white/60 dark:text-gray-300 dark:hover:bg-white/10'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        >
                            <span class="flex gap-1" aria-hidden="true">
                                <span class="size-3 rounded-full" style="background-color: #047857"></span>
                                {{-- White needs its own hairline on the pale
                                     control; the other dots carry themselves. --}}
                                <span class="size-3 rounded-full" style="background-color: #ffffff; box-shadow: inset 0 0 0 1px #d1d5db"></span>
                            </span>

                            <span>Ai2x</span>
                        </button>

                        <button
                            type="button"
                            x-on:click="setPalette('dingdang')"
                            :aria-pressed="palette === 'dingdang'"
                            aria-label="DingDang pink and purple palette"
                            title="DingDang pink and purple"
                            :class="palette === 'dingdang'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-600 hover:bg-white/60 dark:text-gray-300 dark:hover:bg-white/10'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        >
                            <span class="flex gap-1" aria-hidden="true">
                                <span class="size-3 rounded-full" style="background-color: #be185d"></span>
                                <span class="size-3 rounded-full" style="background-color: #c084fc"></span>
                            </span>

                            <span>DingDang</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- The floating trigger. The icon swaps to a close mark while open. --}}
        <button
            type="button"
            x-on:click="appearanceOpen = ! appearanceOpen"
            :aria-expanded="appearanceOpen"
            aria-controls="appearance-panel"
            aria-label="Appearance settings"
            :title="appearanceOpen ? 'Hide appearance settings' : 'Show appearance settings'"
            class="flex size-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 active:scale-95"
        >
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" x-show="! appearanceOpen">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z" />
            </svg>

            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" x-show="appearanceOpen" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{--
        Outside the flex wrapper so a notification is never clipped by the
        sidebar's overflow, and inside <body> so it outlives a page navigation.
    --}}
    <x-toast />
</body>
</html>
