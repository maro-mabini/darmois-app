<?php

use App\Models\RecordsOwner;

/*
 * The layout is a view like any other, so a typo in it produces a parse error
 * at render time. That surfaced as HTTP 200 with a zero-byte body: the page
 * content was swallowed into a $slot that nothing ever printed. These tests
 * assert the shell *and* the page content both survive, so that failure mode
 * cannot come back unnoticed.
 */

test('the layout renders the document shell', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('<html', escape: false)
        ->assertSee('<title>', escape: false)
        ->assertSee('id="app-sidebar"', escape: false);
});

test('the brand is the DAR seal, in the sidebar and in the tab', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('storage/dar_logo.png', escape: false)
        ->assertSee('<link rel="icon"', escape: false)
        ->assertSee('aria-label="DARMOIS home"', escape: false);
});

test('the layout renders the page content into the sidebar slot', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    $response = $this->get(route('admin.records.index'))->assertOk();

    // Guards the empty-slot regression: both halves must be in one document.
    expect($response->getContent())->toContain('app-sidebar')
        ->and($response->getContent())->toContain('Ada Lovelace');
});

test('the sidebar exposes a collapse control that switches the width', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('collapsed = ! collapsed', escape: false)
        ->assertSee("collapsed ? 'lg:w-20' : 'lg:w-64'", escape: false);
});

test('the sidebar persists the collapsed state', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('darmois:sidebar-collapsed', escape: false);
});

test('the sidebar offers a drawer for small screens', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('Open navigation', escape: false)
        ->assertSee('Close navigation', escape: false);
});

test('the sidebar links to the real sections', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    preg_match('/<nav[^>]*aria-label="Main".*?<\/nav>/s', $content, $match);
    $nav = $match[0] ?? '';

    expect($nav)->toContain(route('admin.dashboard'))
        ->and($nav)->toContain(route('admin.records.index'))
        ->and($nav)->toContain(route('admin.settings.index'))
        // The configuration pages are reached through Settings now. Their
        // links still appear in page content (the dashboard headings point
        // at them), so only the nav itself is held to the new shape.
        ->and($nav)->not->toContain(route('admin.record-types.index'))
        ->and($nav)->not->toContain(route('admin.owner-types.index'))
        ->and($nav)->not->toContain(route('admin.locations.index'))
        ->and($nav)->not->toContain(route('admin.stations.index'))
        // Import is a configuration task too, so it lives behind Settings.
        ->and($nav)->not->toContain(route('admin.import.index'));
});

test('the sidebar marks the current section for assistive technology', function () {
    $this->get(route('admin.record-types.index'))
        ->assertOk()
        ->assertSee('aria-current="page"', escape: false);
});

test('the appearance control offers light, dark, and system themes', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('aria-label="Appearance settings"', escape: false)
        ->assertSee("setTheme('system')", escape: false)
        ->assertSee("setTheme('light')", escape: false)
        ->assertSee("setTheme('dark')", escape: false);
});

test('the collapse toggle floats on the sidebar edge and re-centres when collapsed', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    $toggle = preg_match('/<button[^>]*x-on:click="collapsed[^>]*>/', $content, $m) ? $m[0] : '';

    // Expanded: left-full + -translate-x-1/2 straddles the sidebar border.
    // Collapsed: left-1/2 centres it over the brand mark, which it must cover.
    expect($toggle)->toContain("collapsed ? 'left-1/2' : 'left-full'")
        ->and($toggle)->toContain('-translate-x-1/2')
        // z-50 puts the drawer above the mobile scrim; 500 keeps the toggle
        // above the page content it overhangs.
        ->and($toggle)->toContain('z-[500]')
        ->and($toggle)->toContain('size-10');
});

test('the collapse toggle stays hidden until the sidebar is hovered', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    preg_match('/<button[^>]*x-on:click="collapsed[^>]*>/', $content, $m);
    $toggle = $m[0] ?? '';

    expect($toggle)->toContain('opacity-0')
        ->and($toggle)->toContain('group-hover/sidebar:opacity-100')
        ->and($toggle)->toContain('group-hover/sidebar:pointer-events-auto');
});

test('the hidden collapse toggle cannot swallow clicks on the brand link', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    preg_match('/<button[^>]*x-on:click="collapsed[^>]*>/', $content, $m);
    $toggle = $m[0] ?? '';

    // A 40px invisible button over the logo would eat clicks aimed at it.
    expect($toggle)->toContain('pointer-events-none');
});

test('the collapse toggle is still reachable by keyboard', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    preg_match('/<button[^>]*x-on:click="collapsed[^>]*>/', $content, $m);
    $toggle = $m[0] ?? '';

    expect($toggle)->toContain('focus-visible:opacity-100')
        ->and($toggle)->toContain('focus-visible:pointer-events-auto')
        // The visible glyph is an icon, so it needs a text name for screen readers.
        ->and($content)->toContain('Toggle sidebar')
        ->and($toggle)->toContain(':aria-expanded');
});

test('the collapse toggle shows the arrow that matches the state it is leaving', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    preg_match_all('/<g x-show="(.*?)" x-cloak>\s*<path[^>]*d="([^"]+)"/', $content, $m, PREG_SET_ORDER);

    $icons = [];

    foreach ($m as $match) {
        $icons[trim($match[1])] = $match[2];
    }

    // Heroicons arrow-*-end-on-rectangle: the rectangle is the sidebar, so the
    // arrow points the way the panel is about to move. Expanded collapses it
    // (arrow out), collapsed brings it back (arrow in). Swapping these two is
    // the whole bug, and nothing else in the suite would notice.
    expect($icons)->toHaveKeys(['! collapsed', 'collapsed'])
        ->and($icons['! collapsed'])->toBe('M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75')
        ->and($icons['collapsed'])->toBe('M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25');
});

test('both collapse arrows are cloaked so the wrong one cannot flash first', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    // The persisted state is restored by Alpine after first paint, so whichever
    // arrow is not the initial one has to be hidden before that happens.
    expect(substr_count($content, '<g x-show='))->toBe(2)
        ->and(substr_count($content, 'x-cloak>'))->toBeGreaterThanOrEqual(2);
});

test('the collapse toggle no longer spins a single glyph', function () {
    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    // Scoped to the toggle itself: other controls on the page (a dropdown's
    // chevron, for instance) are free to use rotate-180 for their own states.
    preg_match('/<button[^>]*aria-controls="app-sidebar".*?<\/button>/s', $content, $match);
    $toggle = $match[0] ?? '';

    // The old double chevron was rotated 180deg when collapsed. Two distinct
    // arrows replace it, so the rotation must be gone or it would spin on top.
    expect($toggle)->not->toBeEmpty()
        ->and($toggle)->not->toContain('rotate-180')
        ->and($toggle)->not->toContain('M11 19l-7-7 7-7');
});

test('every page route renders the same layout', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('id="app-sidebar"', escape: false);
})->with([
    'dashboard' => 'admin.dashboard',
    'records' => 'admin.records.index',
    'record-types' => 'admin.record-types.index',
    'owner-types' => 'admin.owner-types.index',
]);

test('the record page renders the same layout', function () {
    $record = RecordsOwner::factory()->create();

    $this->get(route('admin.records.show', $record))
        ->assertOk()
        ->assertSee('id="app-sidebar"', escape: false);
});
