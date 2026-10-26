<?php

use App\Models\File;
use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Models\Station;

/*
 * These go through the real router rather than Livewire::test(). A Livewire page
 * component only receives a route parameter when its mount() parameter is named
 * the same as the route parameter, and a mismatch fails only at request time --
 * every Livewire::test() call passes parameters explicitly, so it cannot detect it.
 *
 * The create and edit forms are modals inside their parent page now, so the
 * "does this page render" coverage here is really "does the modal render".
 */

test('the records index renders', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('Ada Lovelace');
});

test('the record form modal renders the owner and record type options', function () {
    RecordType::factory()->create(['name' => 'Land Registry']);
    OwnerType::factory()->create(['name' => 'None ARB']);

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('<dialog', escape: false)
        ->assertSee('New record')
        ->assertSee('Land Registry')
        ->assertSee('None ARB');
});

test('the record detail page renders its owner, type and files', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    $record->update(['record_type_id' => RecordType::factory()->create(['name' => 'Land Registry'])->id]);
    File::factory()->for($record)->create(['file_name' => 'Deed of transfer.pdf']);

    $this->get(route('admin.records.show', $record))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Land Registry')
        ->assertSee('Deed of transfer.pdf');
});

test('the record page renders the edit and file modals', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    RecordType::factory()->create(['name' => 'Land Registry']);
    OwnerType::factory()->create(['name' => 'ARBO']);

    $this->get(route('admin.records.show', $record))
        ->assertOk()
        ->assertSee('Edit record')
        ->assertSee('Add file')
        ->assertSee('Land Registry')
        ->assertSee('ARBO');
});

test('a record detail page is not found for a missing record', function () {
    $this->get('/records/999999')->assertNotFound();
});

test('the record types index renders', function () {
    RecordType::factory()->create(['name' => 'Land Registry']);

    $this->get(route('admin.record-types.index'))
        ->assertOk()
        ->assertSee('Land Registry')
        ->assertSee('<dialog', escape: false);
});

test('the record type form modal renders', function () {
    $this->get(route('admin.record-types.index'))
        ->assertOk()
        ->assertSee('New record type');
});

test('the locations index renders', function () {
    Location::factory()->create(['name' => 'Barangay San Isidro']);

    $this->get(route('admin.locations.index'))
        ->assertOk()
        ->assertSee('Barangay San Isidro')
        ->assertSee('<dialog', escape: false)
        ->assertSee('New location');
});

test('the stations index renders', function () {
    Station::factory()->create(['name' => 'DAR Station I']);

    $this->get(route('admin.stations.index'))
        ->assertOk()
        ->assertSee('DAR Station I')
        ->assertSee('<dialog', escape: false)
        ->assertSee('New station');
});

test('the record type index carries the row names for the delete dialog', function () {
    $recordType = RecordType::factory()->create(['name' => 'Land Registry']);

    $content = $this->get(route('admin.record-types.index'))->assertOk()->getContent();

    // The dialog is filled from the DOM, so the label has to survive to the
    // rendered attribute rather than only existing on the server.
    expect($content)->toContain('data-label="Land Registry"', escape: false)
        ->and($content)->toContain('id: \'record-type-delete\'', escape: false);
});

test('the records table lists the owner, owner type and record type', function () {
    $recordType = RecordType::factory()->create(['name' => 'Land Registry']);

    $record = RecordsOwner::factory()->create([
        'owner_name' => 'Ada Lovelace',
        'owner_type_id' => OwnerType::factory()->create(['name' => 'ARBO'])->id,
        'record_type_id' => $recordType->id,
    ]);

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('ARBO')
        ->assertSee('Land Registry')
        ->assertSee(route('admin.records.show', $record), escape: false);
});

test('a record row toggles its file panel instead of navigating', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    File::factory()->for($record)->create(['file_name' => 'Deed of transfer.pdf']);

    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    expect($content)
        // The row used to navigate. It now discloses its files, and the owner
        // name and View action are the way to the record page.
        ->not->toContain('Livewire.navigate', escape: false)
        ->toContain(':aria-expanded="open"', escape: false)
        ->toContain('id="record-files-'.$record->id.'"', escape: false)
        ->toContain('Deed of transfer.pdf');
});

test('the record actions offer view, add file, edit and delete', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    expect(substr_count($content, route('admin.records.show', $record)))
        // Once on the owner name, once on View.
        ->toBeGreaterThanOrEqual(2)
        ->and($content)->toContain('View', 'Add file', 'Edit', 'Delete')
        // Adding from the row also expands it, so the saved file is visible.
        ->toContain('startCreateFile('.$record->id.')', escape: false)
        ->toContain('open = true', escape: false);
});

test('a record row click ignores clicks on its own links and buttons', function () {
    RecordsOwner::factory()->create();

    $content = $this->get(route('admin.records.index'))->assertOk()->getContent();

    // Without this guard the row would collapse the moment Edit, Delete or the
    // owner name was pressed.
    expect($content)->toContain("closest('a, button, input, label')", escape: false)
        ->and($content)->toContain('cursor-pointer', escape: false);
});

test('the record detail page lists the files belonging to that record', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    $file = File::factory()->for($record)->create(['file_name' => 'Deed.pdf']);

    $content = $this->get(route('admin.records.show', $record))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Deed.pdf')
        ->getContent();

    // The row carries the id the delete dialog hands to the server, scoped
    // through the record so one record's file cannot be deleted from another.
    expect($content)->toContain('startEditFile('.$file->id.')', escape: false)
        ->and($content)->toContain('id: '.$file->id.', label: $el.dataset.label', escape: false);
});

test('the record detail page does not list another record files', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    $other = RecordsOwner::factory()->create();

    File::factory()->for($other)->create(['file_name' => 'OtherRecord.pdf']);

    $this->get(route('admin.records.show', $record))
        ->assertOk()
        ->assertDontSee('OtherRecord.pdf')
        ->assertSee('No files on this record yet.');
});

test('the retired create and edit pages are gone', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'record create' => '/records/create',
    'record type create' => '/record-types/create',
    'owner type create' => '/owner-types/create',
]);

test('the retired record and file edit pages are gone', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create();

    $this->get("/records/{$record->id}/edit")->assertNotFound();
    $this->get("/records/{$record->id}/files/create")->assertNotFound();
    $this->get("/records/{$record->id}/files/{$file->id}/edit")->assertNotFound();
    $this->get("/record-types/{$record->id}/edit")->assertNotFound();
    $this->get("/owner-types/{$record->id}/edit")->assertNotFound();
});
