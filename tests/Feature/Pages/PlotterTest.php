<?php

use App\Models\Plotter;
use Livewire\Livewire;

/**
 * A closed square, so anything drawn from it has a known shape.
 */
function squareTieLines(): string
{
    return implode("\n", [
        'LINE: 1, BEARING: N. 90 degree, E, DISTANCE: 100 M.',
        'LINE: 2, BEARING: S. 0 degree, E, DISTANCE: 100 M.',
        'LINE: 3, BEARING: N. 90 degree, W, DISTANCE: 100 M.',
        'LINE: 4, BEARING: N. 0 degree, W, DISTANCE: 100 M.',
    ]);
}

test('the plotter page renders through the router', function () {
    $this->get(route('admin.plotter.index'))
        ->assertOk()
        ->assertSee('Plotter')
        ->assertSee('Technical description');
});

test('the plotter is offered in the navigation', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.plotter.index'), escape: false);
});

test('saves a plotter with its description', function () {
    Livewire::test('admin::plotter.index')
        ->set('title_number', 'TCT-0001')
        ->set('lot_number', 'Lot 7')
        ->set('survey_number', 'PSD-00-123')
        ->set('title_name', 'Juan Dela Cruz')
        ->set('area', '1,200 sq. m.')
        ->set('location', 'Barangay San Isidro')
        ->set('raw_text', squareTieLines())
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Plotter saved.');

    $plotter = Plotter::query()->sole();

    expect($plotter)
        ->title_number->toBe('TCT-0001')
        ->lot_number->toBe('Lot 7')
        ->survey_number->toBe('PSD-00-123')
        ->title_name->toBe('Juan Dela Cruz')
        ->area->toBe('1,200 sq. m.')
        ->location->toBe('Barangay San Isidro')
        ->raw_text->toBe(squareTieLines());
});

test('leaves the optional fields empty rather than storing blanks', function () {
    Livewire::test('admin::plotter.index')
        ->set('title_number', 'TCT-0001')
        ->set('lot_number', 'Lot 7')
        ->set('survey_number', 'PSD-00-123')
        ->set('title_name', '')
        ->set('area', '')
        ->set('location', '')
        ->set('raw_text', squareTieLines())
        ->call('save')
        ->assertHasNoErrors();

    expect(Plotter::query()->sole())
        ->title_name->toBeNull()
        ->area->toBeNull()
        ->location->toBeNull();
});

test('requires the title, lot, survey and description', function () {
    Livewire::test('admin::plotter.index')
        ->call('save')
        ->assertHasErrors(['title_number', 'lot_number', 'survey_number', 'raw_text']);

    expect(Plotter::query()->count())->toBe(0);
});

test('refuses a description it cannot read', function () {
    Livewire::test('admin::plotter.index')
        ->set('title_number', 'TCT-0001')
        ->set('lot_number', 'Lot 7')
        ->set('survey_number', 'PSD-00-123')
        ->set('raw_text', 'bounded on the north by a stone wall ten metres long')
        ->call('save')
        ->assertHasErrors('raw_text');

    expect(Plotter::query()->count())->toBe(0);
});

test('draws the shape with a dimension on every side', function () {
    Livewire::test('admin::plotter.index')
        ->set('raw_text', squareTieLines())
        ->assertSee('<polygon points="', escape: false)
        ->assertSee('paint-order="stroke"', escape: false);

    // The form is empty, so there is nothing to draw yet.
    Livewire::test('admin::plotter.index')
        ->assertSee('Nothing to plot yet.');
});

/**
 * The dimensions would crowd the shape at a glance, so they are only inked in
 * when the plot is hovered, on a touch screen, or on paper.
 */
test('keeps the dimensions hidden until hover, touch, or print', function () {
    Livewire::test('admin::plotter.index')
        ->set('raw_text', squareTieLines())
        ->assertSee('opacity-0 transition-opacity', escape: false)
        ->assertSee('group-hover:opacity-100', escape: false)
        ->assertSee('pointer-coarse:opacity-100!', escape: false)
        ->assertSee('print:opacity-100!', escape: false);
});

test('heads the plot with its parcel when it is printed', function () {
    Livewire::test('admin::plotter.index')
        ->set('lot_number', '7')
        ->set('survey_number', 'PSD-00-123')
        ->set('area', '1,200 sq. m.')
        ->set('raw_text', squareTieLines())
        ->assertSee('Plot — Lot 7, Survey PSD-00-123')
        ->assertSee('1,200 sq. m.')
        ->assertSee('x-on:click="window.print()"', escape: false);
});

test('says how many tie lines could not be read', function () {
    Livewire::test('admin::plotter.index')
        ->set('raw_text', implode("\n", [
            'LINE: 1, BEARING: NORTH, DISTANCE: 50 M.',
            'LINE: 2, BEARING: N. 45 degree, E, DISTANCE: 100 M.',
        ]))
        ->assertSee('1 of the 2 lines written');
});

test('reports a description that does not close on itself', function () {
    Livewire::test('admin::plotter.index')
        ->set('raw_text', implode("\n", [
            'LINE: 1, BEARING: N. 90 degree, E, DISTANCE: 100 M.',
            'LINE: 2, BEARING: S. 0 degree, E, DISTANCE: 100 M.',
        ]))
        ->assertSee('Does not close');
});

test('a closed description is not reported as open', function () {
    Livewire::test('admin::plotter.index')
        ->set('raw_text', squareTieLines())
        ->assertDontSee('Does not close');
});

test('lists the saved plotters', function () {
    Plotter::factory()->create(['title_number' => 'TCT-777']);

    Livewire::test('admin::plotter.index')
        ->assertSee('TCT-777');
});

test('opens a saved plotter back into the form', function () {
    $plotter = Plotter::factory()->create([
        'title_number' => 'TCT-777',
        'title_name' => 'Maria Santos',
    ]);

    Livewire::test('admin::plotter.index')
        ->call('startEdit', $plotter->id)
        ->assertSet('editing', $plotter->id)
        ->assertSet('title_number', 'TCT-777')
        ->assertSet('title_name', 'Maria Santos')
        ->assertSet('raw_text', $plotter->raw_text);
});

test('updates the plotter behind the form', function () {
    $plotter = Plotter::factory()->create(['title_number' => 'TCT-777']);

    Livewire::test('admin::plotter.index')
        ->call('startEdit', $plotter->id)
        ->set('title_number', 'TCT-888')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Plotter updated.');

    expect(Plotter::query()->count())->toBe(1)
        ->and(Plotter::query()->sole()->title_number)->toBe('TCT-888');
});

test('deletes a plotter', function () {
    $plotter = Plotter::factory()->create(['title_number' => 'TCT-777']);

    Livewire::test('admin::plotter.index')
        ->call('delete', $plotter->id)
        ->assertDispatched('modal-saved', 'plotter-delete')
        ->assertDispatched('toast', message: 'Plotter “TCT-777” deleted.');

    expect(Plotter::query()->count())->toBe(0);
});

test('deleting the plotter open in the form empties the form', function () {
    $plotter = Plotter::factory()->create();

    Livewire::test('admin::plotter.index')
        ->call('startEdit', $plotter->id)
        ->call('delete', $plotter->id)
        ->assertSet('editing', null)
        ->assertSet('title_number', '')
        ->assertSet('raw_text', '');
});

test('the list carries a confirmation dialog for deleting', function () {
    Plotter::factory()->create(['title_number' => 'TCT-777']);

    Livewire::test('admin::plotter.index')
        ->assertSee('aria-labelledby="plotter-delete-title"', escape: false)
        ->assertSee('Delete plotter')
        ->assertSee("id: 'plotter-delete'", escape: false);
});

test('deleting the last row of a page falls back to a page that still exists', function () {
    $oldest = Plotter::factory()->create(['title_number' => 'TCT-OLDEST']);
    Plotter::factory()->count(15)->create();

    $newest = Plotter::query()->orderByDesc('id')->first();

    Livewire::test('admin::plotter.index')
        ->call('setPage', 2)
        ->call('delete', $oldest->id)
        ->assertSee($newest->title_number)
        ->assertDontSee('No plotters saved yet.');
});

test('plots and saves a description written as corner pairs', function () {
    $description = implode("\n", [
        '0-1 S. 57 48 E 2360,42 M',
        '1-2 N. 00 52 W 10.78 M',
        '2-3 N. 01 15 W 69.96 M.',
    ]);

    Livewire::test('admin::plotter.index')
        ->set('title_number', 'TCT-0001')
        ->set('lot_number', 'Lot 7')
        ->set('survey_number', 'PSD-00-123')
        ->set('raw_text', $description)
        ->assertSee('<polygon points="', escape: false)
        // The decimal comma distance, formatted the way the plot labels it.
        ->assertSee('2,360.42 M')
        ->call('save')
        ->assertHasNoErrors();

    expect(Plotter::query()->sole()->raw_text)->toBe($description);
});

test('the description reaches the plot as it is typed', function () {
    Livewire::test('admin::plotter.index')
        ->assertSee('wire:model.live.debounce.400ms="raw_text"', escape: false)
        ->assertSee('x-on:keydown.enter="$wire.set(', escape: false);
});

test('offers zoom and pan controls over the drawing', function () {
    Livewire::test('admin::plotter.index')
        ->set('raw_text', squareTieLines())
        ->assertSee('aria-label="Zoom in"', escape: false)
        ->assertSee('aria-label="Zoom out"', escape: false)
        ->assertSee('x-on:wheel.prevent="wheel($event)"', escape: false)
        // The transform reads its zoom from CSS custom properties, so a
        // re-render cannot reset the view the user has panned into.
        ->assertSee('var(--plotter-zoom', escape: false)
        ->assertSee('wire:ignore', escape: false);

    // With nothing drawn there is nothing to zoom.
    Livewire::test('admin::plotter.index')
        ->assertDontSee('aria-label="Zoom in"', escape: false);
});

test('lists the name on title with the saved plotter', function () {
    Plotter::factory()->create(['title_number' => 'TCT-777', 'title_name' => 'Maria Santos']);

    Livewire::test('admin::plotter.index')
        ->assertSee('Name on title')
        ->assertSee('Maria Santos');
});

test('searches the saved plotters by name on title regardless of case', function () {
    Plotter::factory()->create(['title_name' => 'Maria Santos']);
    Plotter::factory()->create(['title_name' => 'Pedro Reyes']);

    Livewire::test('admin::plotter.index')
        ->set('search', 'santos')
        ->assertSee('Santos')
        ->assertDontSee('Reyes');
});

test('searches the saved plotters by any label of the parcel', function (string $search) {
    Plotter::factory()->create([
        'title_number' => 'TCT-1111',
        'lot_number' => 'LOT-11',
        'survey_number' => 'PSD-11111',
        'location' => 'Cabinet 1',
        'title_name' => 'Ana Cruz',
    ]);
    Plotter::factory()->create([
        'title_number' => 'TCT-2222',
        'lot_number' => 'LOT-22',
        'survey_number' => 'PSD-22222',
        'location' => 'Cabinet 2',
        'title_name' => 'Ben Lim',
    ]);

    Livewire::test('admin::plotter.index')
        ->set('search', $search)
        ->assertSee('Ben Lim')
        ->assertDontSee('Ana Cruz');
})->with([
    'title number' => 'TCT-2222',
    'lot' => 'LOT-22',
    'survey' => 'PSD-22222',
    'location' => 'Cabinet 2',
]);

test('treats a blank or whitespace search as no filter', function (string $search) {
    Plotter::factory()->create(['title_name' => 'Maria Santos']);
    Plotter::factory()->create(['title_name' => 'Pedro Reyes']);

    Livewire::test('admin::plotter.index')
        ->set('search', $search)
        ->assertSee('Maria Santos')
        ->assertSee('Pedro Reyes')
        ->assertSet('isFiltered', false);
})->with([
    'empty' => '',
    'spaces' => '   ',
]);

test('a search matching nothing says so', function () {
    Plotter::factory()->create(['title_name' => 'Maria Santos']);

    Livewire::test('admin::plotter.index')
        ->set('search', 'nobody at all')
        ->assertDontSee('Maria Santos')
        ->assertSee('No plotters match your search.')
        ->assertSet('isFiltered', true);
});

test('counts only the matching plotters', function () {
    Plotter::factory()->create(['title_name' => 'Maria Santos']);
    Plotter::factory()->create(['title_name' => 'Maria Cruz']);
    Plotter::factory()->create(['title_name' => 'Pedro Reyes']);

    Livewire::test('admin::plotter.index')
        ->set('search', 'Maria')
        ->assertSee('2 match your search.')
        ->assertSee('Santos')
        ->assertSee('Cruz')
        ->assertDontSee('Reyes');
});

test('clearing the search brings every plotter back', function () {
    Plotter::factory()->create(['title_name' => 'Maria Santos']);
    Plotter::factory()->create(['title_name' => 'Pedro Reyes']);

    Livewire::test('admin::plotter.index')
        ->set('search', 'Maria')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('isFiltered', false)
        ->assertSee('Maria Santos')
        ->assertSee('Pedro Reyes');
});

test('a search resets pagination to the first page', function () {
    Plotter::factory()
        ->count(20)
        ->sequence(...array_map(
            fn (int $day) => ['title_number' => sprintf('TCT-%04d', $day), 'created_at' => now()->subDays($day)],
            range(0, 19),
        ))
        ->create();

    Livewire::test('admin::plotter.index')
        ->set('paginators.page', 2)
        ->assertSee('TCT-0000', escape: false)
        ->set('search', 'TCT-0019')
        ->assertSet('paginators.page', 1)
        ->assertSee('TCT-0019');
});

test('the search lives in the url so a filtered list can be shared', function () {
    Plotter::factory()->create(['title_name' => 'Maria Santos']);
    Plotter::factory()->create(['title_name' => 'Pedro Reyes']);

    $this->get(route('admin.plotter.index', ['search' => 'Santos']))
        ->assertOk()
        ->assertSee('Santos')
        ->assertDontSee('Reyes');
});

test('the url cannot be used to break the plotter query', function () {
    $plotter = Plotter::factory()->create(['title_name' => 'Maria Santos']);

    $this->get(route('admin.plotter.index', ['search' => "'; drop table plotters; --"]))
        ->assertOk()
        ->assertSee('No plotters match your search.');

    expect(Plotter::query()->whereKey($plotter->id)->exists())->toBeTrue();
});

test('a percent sign is searched for literally instead of matching everything', function () {
    Plotter::factory()->create(['title_number' => 'TCT-50%']);
    Plotter::factory()->create(['title_number' => 'TCT-5001']);

    Livewire::test('admin::plotter.index')
        ->set('search', '%')
        ->assertSee('TCT-50')
        ->assertDontSee('TCT-5001');
});

test('an underscore is searched for literally instead of matching any character', function () {
    Plotter::factory()->create(['title_number' => 'TCT_A']);
    Plotter::factory()->create(['title_number' => 'TCTXA']);

    Livewire::test('admin::plotter.index')
        ->set('search', 'TCT_A')
        ->assertSee('TCT_A')
        ->assertDontSee('TCTXA');
});

test('the search box is debounced so typing does not query per keystroke', function () {
    $this->get(route('admin.plotter.index'))
        ->assertOk()
        ->assertSee('wire:model.live.debounce.300ms="search"', escape: false);
});

test('highlights the search term in the saved list', function () {
    Plotter::factory()->create(['title_name' => 'Maria Santos']);

    Livewire::test('admin::plotter.index')
        ->set('search', 'Santos')
        ->assertSee('<mark', escape: false)
        ->assertSee('Santos</mark>', escape: false);
});

test('downloads the plot on screen as a drawing file', function () {
    Livewire::test('admin::plotter.index')
        ->set('title_number', 'TCT-0001')
        ->set('lot_number', 'Lot 7')
        ->set('survey_number', 'PSD-00-123')
        ->set('title_name', 'Juan Dela Cruz')
        ->set('raw_text', squareTieLines())
        ->call('download')
        ->assertFileDownloaded('plot-tct-0001-lot-7.dxf', null, 'application/dxf');
});

test('there is nothing to download until the description can be read', function () {
    Livewire::test('admin::plotter.index')
        ->call('download')
        ->assertNoFileDownloaded()
        ->assertDispatched('toast', message: 'There is nothing to download yet.');
});

test('offers the download next to the print button', function () {
    Livewire::test('admin::plotter.index')
        ->set('raw_text', squareTieLines())
        ->assertSee('Download DXF')
        ->assertSee('$wire.download()', escape: false);
});
