<?php

/*
 * The page canvas is set on <body> in the layout, so these assert the layout
 * rather than any individual page. Dark mode silently broke once already: the
 * body had no background, so `dark:text-white` headings rendered as white text
 * on the browser's default white page.
 *
 * NOTE: this file must start with `<?php`. A Pest test file without an opening
 * tag is not discovered at all, and Pest then reports a green suite.
 */

/**
 * The Blade views that are expected to work in both color schemes.
 *
 * @return list<string>
 */
function themeViews(): array
{
    return array_merge(
        glob(resource_path('views/admin/*.blade.php')),
        glob(resource_path('views/admin/records/*.blade.php')),
        glob(resource_path('views/admin/records/files/*.blade.php')),
        glob(resource_path('views/admin/record-types/*.blade.php')),
        glob(resource_path('views/components/form/*.blade.php')),
    );
}

test('there are views to check', function () {
    expect(themeViews())->not->toBeEmpty();
});

/*
 * Returns the opening <body> tag of a rendered page.
 *
 * A naive '/<body[^>]*>/' stops at the first ">", which lands inside the
 * Alpine x-data attribute because it contains "=>" arrow functions -- so the
 * class attribute after it would never be seen. This pattern walks quoted
 * attribute values so any ">" inside them is ignored.
 */
function bodyTag(): string
{
    $html = test()->get(route('admin.records.index'))->assertOk()->getContent();

    preg_match('/<body\b(?:[^>"]|"[^"]*")*>/', $html, $matches);

    return $matches[0] ?? '';
}

test('the layout paints a light and a dark page canvas', function () {
    $body = bodyTag();

    // Matched against the <body> tag itself, so one of the two classes
    // satisfying the search is not enough to hide a missing canvas color.
    expect((bool) preg_match('/\bbg-gray-50\b/', $body))->toBeTrue();
    expect((bool) preg_match('/\bdark:bg-gray-950\b/', $body))->toBeTrue();
});

test('the layout declares text colors for both color schemes', function () {
    $body = bodyTag();

    expect((bool) preg_match('/\btext-gray-900\b/', $body))->toBeTrue();
    expect((bool) preg_match('/\bdark:text-gray-100\b/', $body))->toBeTrue();
});

test('the stylesheet declares a color scheme for browser painted surfaces', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('color-scheme: light')
        ->and($css)->toContain('color-scheme: dark');
});

test('the dark variant responds to both the system preference and a class', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@custom-variant dark')
        ->and($css)->toContain('prefers-color-scheme: dark');
});

test('dark mode body text is not gray-400, which is only 3.02:1 on gray-900', function () {
    foreach (themeViews() as $view) {
        expect(file_get_contents($view))->not->toContain('dark:text-gray-400');
    }
});

test('dark mode links are not brand-500, which is only 4.05:1 on gray-900', function () {
    foreach (themeViews() as $view) {
        expect(file_get_contents($view))->not->toContain('dark:hover:text-brand-500');
    }

    foreach (themeViews() as $view) {
        expect(file_get_contents($view))->not->toContain('dark:text-brand-500');
    }
});

test('the default palette is the DAR green and gold', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--color-brand-600: #1b6c47')
        ->toContain('--color-gold-400: #f5c518');

    $views = array_merge(
        themeViews(),
        glob(resource_path('views/layouts/*/*.blade.php')),
        glob(resource_path('views/components/*.blade.php')),
    );

    // Views name `brand-*` and `gold-*`, never one palette's own colour, so
    // the two palettes cannot drift apart. `indigo-*` stands in for any
    // Tailwind palette utility a view might reach for directly.
    foreach ($views as $view) {
        expect(file_get_contents($view))->not->toContain('indigo-');
    }
});

test('the iris palette re-points both ramps, so no view needs palette classes', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect(preg_match("/:root\[data-palette='iris'\]\s*\{(.*?)\}/s", $css, $matches))->toBe(1);

    $iris = $matches[1];

    expect($iris)
        ->toContain('--color-brand-50')
        ->toContain('--color-brand-600')
        ->toContain('--color-brand-950')
        ->toContain('--color-gold-300')
        ->toContain('--color-gold-400')
        ->toContain('--color-gold-950')
        // The DAR values must not leak through: the whole point is that the
        // same variable is read by both palettes.
        ->not->toContain('#1b6c47')
        ->not->toContain('#f5c518');
});

test('the sidebar offers both palettes and remembers the choice', function () {
    $html = test()->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)
        ->toContain('aria-label="Color palette"')
        ->toContain("setPalette('dar')")
        ->toContain("setPalette('iris')")
        ->toContain('darmois:palette');
});

test('dark mode placeholders are not gray-400, which is only 2.73:1 on the input background', function () {
    foreach (themeViews() as $view) {
        expect(file_get_contents($view))->not->toContain('dark:placeholder:text-gray-400');
    }
});

test('an explicit light choice overrules a dark system preference', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain(':root:not(.light)');
});

test('no theme test file is missing its opening tag', function () {
    foreach (glob(__DIR__.'/*.php') as $test) {
        expect(str_starts_with(file_get_contents($test), '<?php'))
            ->toBeTrue(basename($test).' is missing its <?php opening tag, so Pest will silently skip it');
    }
});
