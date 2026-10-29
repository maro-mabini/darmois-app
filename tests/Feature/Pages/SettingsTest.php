<?php

test('the settings page offers every configuration section', function () {
    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Record types')
        ->assertSee('Owner types')
        ->assertSee('Locations')
        ->assertSee('Stations')
        ->assertSee(route('admin.record-types.index'), escape: false)
        ->assertSee(route('admin.owner-types.index'), escape: false)
        ->assertSee(route('admin.locations.index'), escape: false)
        ->assertSee(route('admin.stations.index'), escape: false)
        ->assertSee(route('admin.import.index'), escape: false);
});

test('the sidebar keeps the configuration pages behind settings', function () {
    $content = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    preg_match('/<nav[^>]*aria-label="Main".*?<\/nav>/s', $content, $match);
    $nav = $match[0] ?? '';

    expect($nav)->toContain(route('admin.settings.index'))
        ->and($nav)->not->toContain(route('admin.record-types.index'))
        ->and($nav)->not->toContain(route('admin.owner-types.index'))
        ->and($nav)->not->toContain(route('admin.locations.index'))
        ->and($nav)->not->toContain(route('admin.stations.index'))
        ->and($nav)->not->toContain(route('admin.import.index'));
});

test('the settings link marks itself current on a configuration page', function (string $route) {
    $content = $this->get(route($route))
        ->assertOk()
        ->getContent();

    preg_match(
        '/<a[^>]*href="'.preg_quote(route('admin.settings.index'), '/').'".*?<\/a>/s',
        $content,
        $match,
    );

    // The configuration pages live behind Settings, so it is the entry that
    // has to light up while the user is in one of them.
    expect($match[0] ?? '')->toContain('aria-current="page"');
})->with([
    'locations' => 'admin.locations.index',
    'import' => 'admin.import.index',
]);

test('every configuration page links back to settings', function (string $route) {
    $content = $this->get(route($route))->assertOk()->getContent();

    // The sidebar carries its own settings link, so take the nav out first:
    // what is left has to be the page's own way back.
    $content = preg_replace('/<nav[^>]*aria-label="Main".*?<\/nav>/s', '', $content) ?? '';

    preg_match(
        '/<a[^>]*href="'.preg_quote(route('admin.settings.index'), '/').'".*?Back to Settings.*?<\/a>/s',
        $content,
        $match,
    );

    expect($match[0] ?? '')->not->toBe('');
})->with([
    'record types' => 'admin.record-types.index',
    'owner types' => 'admin.owner-types.index',
    'locations' => 'admin.locations.index',
    'stations' => 'admin.stations.index',
    'import' => 'admin.import.index',
]);

test('the settings page renders the same layout', function () {
    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('id="app-sidebar"', escape: false);
});
