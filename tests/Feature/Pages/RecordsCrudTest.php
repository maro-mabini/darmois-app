<?php

use App\Models\File;
use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Models\Station;
use Livewire\Livewire;

test('creates a record from the modal', function () {
    $recordType = RecordType::factory()->create();
    $arb = OwnerType::factory()->create(['name' => 'ARB']);

    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', 'Ada Lovelace')
        ->set('owner_type_id', (string) $arb->id)
        ->set('record_type_id', $recordType->id)
        ->call('save')
        ->assertHasNoErrors()
        // The form lives on the index now, so there is nothing to navigate to.
        ->assertNoRedirect()
        ->assertDispatched('modal-saved', 'record-form');

    expect(RecordsOwner::query()->latest('id')->first())
        ->owner_name->toBe('Ada Lovelace')
        ->ownerType->name->toBe('ARB')
        ->recordType->name->toBe($recordType->name);
});

test('requires an owner name, an owner type and a record type', function () {
    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors(['owner_name', 'owner_type_id', 'record_type_id']);

    expect(RecordsOwner::query()->count())->toBe(0);
});

test('offers the record types in the form', function () {
    RecordType::factory()->create(['name' => 'Land Registry']);
    RecordType::factory()->create(['name' => 'Case File']);

    Livewire::test('admin::records.index')
        ->assertSee('Land Registry')
        ->assertSee('Case File');
});

test('offers the owner types in the form', function () {
    OwnerType::factory()->create(['name' => 'ARB']);
    OwnerType::factory()->create(['name' => 'None ARB']);

    Livewire::test('admin::records.index')
        ->assertSee('ARB')
        ->assertSee('None ARB');
});

test('rejects an owner type that does not exist', function () {
    $recordType = RecordType::factory()->create();

    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', 'Ada Lovelace')
        ->set('record_type_id', $recordType->id)
        ->set('owner_type_id', 9999)
        ->call('save')
        ->assertHasErrors('owner_type_id');

    expect(RecordsOwner::query()->count())->toBe(0);
});

test('rejects a record type that does not exist', function () {
    $arb = OwnerType::factory()->create(['name' => 'ARB']);

    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', 'Ada Lovelace')
        ->set('owner_type_id', (string) $arb->id)
        ->set('record_type_id', 9999)
        ->call('save')
        ->assertHasErrors('record_type_id');

    expect(RecordsOwner::query()->count())->toBe(0);
});

test('a failed record save reopens the form', function () {
    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors(['owner_name', 'owner_type_id', 'record_type_id'])
        ->assertDispatched('modal-open', 'record-form');
});

test('edits a record', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Old Name']);
    $recordType = RecordType::factory()->create(['name' => 'Land Registry']);
    $arbo = OwnerType::factory()->create(['name' => 'ARBO']);

    Livewire::test('admin::records.index')
        ->call('startEdit', $record->id)
        ->assertSet('owner_name', 'Old Name')
        ->set('owner_name', 'New Name')
        ->set('owner_type_id', (string) $arbo->id)
        ->set('record_type_id', $recordType->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($record->fresh())
        ->owner_name->toBe('New Name')
        ->ownerType->name->toBe('ARBO')
        ->recordType->name->toBe('Land Registry');
});

test('records a location and station with the owner', function () {
    $recordType = RecordType::factory()->create();
    $arb = OwnerType::factory()->create(['name' => 'ARB']);
    $location = Location::factory()->create(['name' => 'Barangay San Isidro']);
    $station = Station::factory()->create(['name' => 'DAR Station I']);

    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', 'Ada Lovelace')
        ->set('location_id', (string) $location->id)
        ->set('station_id', (string) $station->id)
        ->set('owner_type_id', (string) $arb->id)
        ->set('record_type_id', $recordType->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(RecordsOwner::query()->latest('id')->first())
        ->owner_name->toBe('Ada Lovelace')
        ->location->name->toBe('Barangay San Isidro')
        ->station->name->toBe('DAR Station I');
});

test('rejects a location or station that does not exist', function () {
    $recordType = RecordType::factory()->create();
    $arb = OwnerType::factory()->create(['name' => 'ARB']);

    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', 'Ada Lovelace')
        ->set('location_id', '9999')
        ->set('station_id', '9999')
        ->set('owner_type_id', (string) $arb->id)
        ->set('record_type_id', $recordType->id)
        ->call('save')
        ->assertHasErrors(['location_id', 'station_id']);

    expect(RecordsOwner::query()->count())->toBe(0);
});

test('a record without a location or station stores nulls', function () {
    $recordType = RecordType::factory()->create();
    $arb = OwnerType::factory()->create(['name' => 'ARB']);

    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', 'Ada Lovelace')
        ->set('location_id', '')
        ->set('station_id', '')
        ->set('owner_type_id', (string) $arb->id)
        ->set('record_type_id', $recordType->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(RecordsOwner::query()->latest('id')->first())
        ->location_id->toBeNull()
        ->station_id->toBeNull();
});

test('loading a record fills in its location and station', function () {
    $location = Location::factory()->create();
    $station = Station::factory()->create();

    $record = RecordsOwner::factory()->create([
        'location_id' => $location->id,
        'station_id' => $station->id,
    ]);

    Livewire::test('admin::records.index')
        ->call('startEdit', $record->id)
        ->assertSet('location_id', (string) $location->id)
        ->assertSet('station_id', (string) $station->id);
});

test('an edit can move a record to a new location and station', function () {
    $record = RecordsOwner::factory()->create([
        'location_id' => Location::factory()->create()->id,
        'station_id' => Station::factory()->create()->id,
    ]);

    $newLocation = Location::factory()->create(['name' => 'Barangay Poblacion']);
    $newStation = Station::factory()->create(['name' => 'DAR Station II']);

    Livewire::test('admin::records.index')
        ->call('startEdit', $record->id)
        ->set('location_id', (string) $newLocation->id)
        ->set('station_id', (string) $newStation->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($record->fresh())
        ->location->name->toBe('Barangay Poblacion')
        ->station->name->toBe('DAR Station II');
});

test('the records table shows each record location and station', function () {
    RecordsOwner::factory()->create([
        'owner_name' => 'Ada Lovelace',
        'location_id' => Location::factory()->create(['name' => 'Barangay San Isidro'])->id,
        'station_id' => Station::factory()->create(['name' => 'DAR Station I'])->id,
    ]);

    Livewire::test('admin::records.index')
        ->assertSee('Location')
        ->assertSee('Station')
        ->assertSee('Barangay San Isidro')
        ->assertSee('DAR Station I');
});

test('the location and station fields are searchable dropdowns', function () {
    Location::factory()->create(['name' => 'Barangay San Isidro']);
    Station::factory()->create(['name' => 'DAR Station I']);

    Livewire::test('admin::records.index')
        ->assertSeeHtml('id="location_id"')
        ->assertSeeHtml('id="location_id-listbox"')
        ->assertSeeHtml('id="station_id"')
        ->assertSeeHtml('id="station_id-listbox"')
        ->assertSee('Search locations…')
        ->assertSee('Search stations…')
        ->assertSee('No location')
        ->assertSee('No station');
});

test('the record page location and station fields are searchable dropdowns', function () {
    Location::factory()->create(['name' => 'Barangay San Isidro']);
    Station::factory()->create(['name' => 'DAR Station I']);

    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->assertSeeHtml('id="location_id-listbox"')
        ->assertSeeHtml('id="station_id-listbox"')
        ->assertSee('Search locations…')
        ->assertSee('Search stations…');
});

test('the owner type and record type fields are searchable dropdowns', function () {
    OwnerType::factory()->create(['name' => 'ARB']);
    RecordType::factory()->create(['name' => 'Case File']);

    Livewire::test('admin::records.index')
        ->assertSeeHtml('id="owner_type_id-listbox"')
        ->assertSeeHtml('id="record_type_id-listbox"')
        ->assertSee('Search owner types…')
        ->assertSee('Search record types…')
        // The list filter is the same searchable control.
        ->assertSeeHtml('id="recordType"')
        ->assertSeeHtml('id="recordType-listbox"')
        ->assertSee('All record types');
});

test('the record page owner type and record type fields are searchable dropdowns', function () {
    OwnerType::factory()->create(['name' => 'ARB']);
    RecordType::factory()->create(['name' => 'Case File']);

    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->assertSeeHtml('id="owner_type_id-listbox"')
        ->assertSeeHtml('id="record_type_id-listbox"')
        ->assertSee('Search owner types…')
        ->assertSee('Search record types…');
});

test('filters the list by record type', function () {
    $ownerType = OwnerType::factory()->create();
    $caseFile = RecordType::factory()->create(['name' => 'Case File']);
    $profile = RecordType::factory()->create(['name' => 'Profile']);

    RecordsOwner::factory()->create([
        'owner_name' => 'Matching Owner',
        'owner_type_id' => $ownerType->id,
        'record_type_id' => $caseFile->id,
    ]);
    RecordsOwner::factory()->create([
        'owner_name' => 'Other Owner',
        'owner_type_id' => $ownerType->id,
        'record_type_id' => $profile->id,
    ]);

    Livewire::test('admin::records.index')
        ->set('recordType', (string) $caseFile->id)
        ->assertSee('Matching Owner')
        ->assertDontSee('Other Owner');
});

test('deletes a record and its files', function () {
    $record = RecordsOwner::factory()->create();
    File::factory()->for($record)->count(2)->create();

    Livewire::test('admin::records.index')
        ->call('delete', $record->id)
        ->assertHasNoErrors()
        ->assertDispatched('modal-saved', 'record-delete');

    expect(RecordsOwner::query()->count())->toBe(0)
        ->and(File::query()->count())->toBe(0);
});

test('adds a file to a record from the records list', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    Livewire::test('admin::records.index')
        ->call('startCreateFile', $record->id)
        ->assertSet('fileRecord', $record->id)
        ->assertSee('Attach a file to Ada Lovelace.')
        ->set('file_name', 'Deed of transfer.pdf')
        ->set('file_description', 'Signed transfer deed')
        ->set('date_of_file', '1893-04-12')
        ->call('saveFile')
        ->assertHasNoErrors()
        ->assertSet('fileRecord', null)
        ->assertDispatched('modal-saved', 'file-form')
        ->assertDispatched('toast', message: 'File added.')
        // The expanded row lists the file without a reload.
        ->assertSee('Deed of transfer.pdf');

    expect($record->files()->count())->toBe(1);

    $file = $record->files()->firstOrFail();

    expect($file)
        ->file_name->toBe('Deed of transfer.pdf')
        ->file_description->toBe('Signed transfer deed')
        ->and($file->date_of_file?->format('Y-m-d'))->toBe('1893-04-12');
});

test('the list file form requires a name and reopens for the same record', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.index')
        ->call('startCreateFile', $record->id)
        ->call('saveFile')
        ->assertHasErrors('file_name')
        ->assertSet('fileRecord', $record->id)
        ->assertDispatched('modal-open', 'file-form');

    expect($record->files()->count())->toBe(0);
});

test('a file cannot be added to a record that does not exist', function () {
    Livewire::test('admin::records.index')
        ->call('startCreateFile', 9999)
        ->assertNotFound();
});

test('saving the list file form without a record stores nothing', function () {
    Livewire::test('admin::records.index')
        ->set('file_name', 'Orphan.pdf')
        ->call('saveFile');

    expect(File::query()->count())->toBe(0);
});

test('shows a record with its files', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    $recordType = RecordType::factory()->create(['name' => 'Land Registry']);
    $record->update(['record_type_id' => $recordType->id]);
    File::factory()->for($record)->create(['file_name' => 'Deed of transfer.pdf']);

    Livewire::test('admin::records.show', ['record' => $record->fresh()])
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Land Registry')
        ->assertSee('Deed of transfer.pdf');
});

test('the record page gives each modal its own dialog ref', function () {
    $record = RecordsOwner::factory()->create();

    $html = Livewire::test('admin::records.show', ['record' => $record])->html();

    preg_match_all('/<dialog\b(?:[^>"]|"[^"]*")*>/', $html, $matches);

    $modals = array_values(array_filter(
        $matches[0],
        fn (string $tag): bool => str_contains($tag, 'x-ref="dialog"'),
    ));

    expect($modals)->not->toBeEmpty();

    foreach ($modals as $modal) {
        // Without an Alpine root of its own, every modal on the page writes the
        // same `dialog` ref into one scope, so they open and close each other.
        expect($modal)->toContain('x-data');
    }
});

test('adds a file to a record', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Deed of transfer.pdf')
        ->set('file_description', 'Signed transfer deed')
        ->set('date_of_file', '1893-04-12')
        ->call('saveFile')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertDispatched('modal-saved', 'file-form');

    expect($record->files()->count())->toBe(1);

    $file = $record->files()->firstOrFail();

    expect($file)
        ->file_name->toBe('Deed of transfer.pdf')
        ->file_description->toBe('Signed transfer deed')
        ->and($file->date_of_file?->format('Y-m-d'))->toBe('1893-04-12');
});

test('stores a blank file description and date as null', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Untitled scan')
        ->set('file_description', '')
        ->set('date_of_file', '')
        ->call('saveFile')
        ->assertHasNoErrors();

    expect($record->files()->firstOrFail())
        ->file_description->toBeNull()
        ->and($record->files()->firstOrFail()->date_of_file)->toBeNull();
});

test('requires a file name', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->call('saveFile')
        ->assertHasErrors('file_name');

    expect($record->files()->count())->toBe(0);
});

test('a failed file save reopens the form', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->call('saveFile')
        ->assertHasErrors('file_name')
        ->assertDispatched('modal-open', 'file-form');
});

test('edits a file', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create(['file_name' => 'Old.pdf']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditFile', $file->id)
        ->assertSet('file_name', 'Old.pdf')
        ->set('file_name', 'New.pdf')
        ->set('date_of_file', '1893-04-12')
        ->call('saveFile')
        ->assertHasNoErrors();

    expect($file->fresh())
        ->file_name->toBe('New.pdf')
        ->and($file->fresh()->date_of_file?->format('Y-m-d'))->toBe('1893-04-12');
});

test('loading a file into the form fills in its whole date', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create([
        'file_name' => 'Deed.pdf',
        'date_of_file' => '1893-04-12',
    ]);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditFile', $file->id)
        ->assertSet('date_of_file', '1893-04-12');
});

test('an untouched date survives an unrelated edit', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create([
        'file_name' => 'Deed.pdf',
        'date_of_file' => '1893-04-12',
    ]);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditFile', $file->id)
        ->set('file_name', 'Amended deed.pdf')
        ->call('saveFile')
        ->assertHasNoErrors();

    expect($file->fresh()->date_of_file?->format('Y-m-d'))->toBe('1893-04-12');
});

test('a new date replaces the old one', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create([
        'file_name' => 'Deed.pdf',
        'date_of_file' => '1893-04-12',
    ]);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditFile', $file->id)
        ->set('date_of_file', '1893-05-02')
        ->call('saveFile')
        ->assertHasNoErrors();

    expect($file->fresh()->date_of_file?->format('Y-m-d'))->toBe('1893-05-02');
});

test('rejects a date that is not a full date', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Half a date.pdf')
        ->set('date_of_file', '1893-04')
        ->call('saveFile')
        ->assertHasErrors('date_of_file');

    expect($record->files()->count())->toBe(0);
});

test('rejects a day that does not exist in the month', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Impossible.pdf')
        ->set('date_of_file', '1893-02-30')
        ->call('saveFile')
        ->assertHasErrors('date_of_file');
});

test('rejects a date in another format', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Slashes.pdf')
        ->set('date_of_file', '12/04/1893')
        ->call('saveFile')
        ->assertHasErrors('date_of_file');
});

test('rejects a date from before the picker starts', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Antique.pdf')
        ->set('date_of_file', '1800-01-01')
        ->call('saveFile')
        ->assertHasErrors('date_of_file');
});

test('rejects a date from beyond the picker', function () {
    $record = RecordsOwner::factory()->create();
    $farFuture = ((int) now()->year + 50).'-01-01';

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Far future.pdf')
        ->set('date_of_file', $farFuture)
        ->call('saveFile')
        ->assertHasErrors('date_of_file');
});

test('the file form offers all twelve months', function () {
    $record = RecordsOwner::factory()->create();

    $months = Livewire::test('admin::records.show', ['record' => $record])
        ->instance()
        ->monthOptions();

    expect($months)->toHaveCount(12)
        ->and(array_keys($months))->toBe(range(1, 12))
        ->and($months[1])->toBe('January')
        ->and($months[12])->toBe('December');
});

test('the picker reaches into the past and a little into the future', function () {
    $record = RecordsOwner::factory()->create();

    $range = Livewire::test('admin::records.show', ['record' => $record])
        ->instance()
        ->fileYearRange();

    $current = (int) now()->year;

    expect($range)->toBe([1850, $current + 5]);
});

test('the picker is bound to the same property the file form validates', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->assertSee('date_of_file')
        ->assertSee('Choose a date');
});

test('the file date is shown to the day it carries', function () {
    $record = RecordsOwner::factory()->create();
    File::factory()->for($record)->create(['date_of_file' => '1893-04-12']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->assertSee('Apr 12, 1893')
        ->assertDontSee('April 1893');
});

test('cannot edit a file through the wrong record', function () {
    $record = RecordsOwner::factory()->create();
    $other = RecordsOwner::factory()->create();
    $file = File::factory()->for($other)->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditFile', $file->id)
        ->assertNotFound();
});

test('edits a record from the record page', function () {
    $location = Location::factory()->create(['name' => 'Barangay San Isidro']);
    $station = Station::factory()->create(['name' => 'DAR Station I']);

    $record = RecordsOwner::factory()->create([
        'owner_name' => 'Old Name',
        'location_id' => $location->id,
        'station_id' => $station->id,
    ]);
    $arbo = OwnerType::factory()->create(['name' => 'ARBO']);
    $newLocation = Location::factory()->create(['name' => 'Barangay Poblacion']);
    $newStation = Station::factory()->create(['name' => 'DAR Station II']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditRecord')
        ->assertSet('owner_name', 'Old Name')
        ->assertSet('location_id', (string) $location->id)
        ->assertSet('station_id', (string) $station->id)
        ->set('owner_name', 'New Name')
        ->set('location_id', (string) $newLocation->id)
        ->set('station_id', (string) $newStation->id)
        ->set('owner_type_id', (string) $arbo->id)
        ->call('saveRecord')
        ->assertHasNoErrors()
        ->assertSet('editingRecord', false)
        ->assertDispatched('modal-saved', 'record-form');

    expect($record->fresh())
        ->owner_name->toBe('New Name')
        ->location->name->toBe('Barangay Poblacion')
        ->station->name->toBe('DAR Station II')
        ->ownerType->name->toBe('ARBO');
});

test('the record page shows the location and station when it has them', function () {
    $record = RecordsOwner::factory()->create([
        'location_id' => Location::factory()->create(['name' => 'Barangay San Isidro'])->id,
        'station_id' => Station::factory()->create(['name' => 'DAR Station I'])->id,
    ]);

    Livewire::test('admin::records.show', ['record' => $record])
        ->assertSee('Barangay San Isidro')
        ->assertSee('DAR Station I');
});

test('deletes the record from the record page', function () {
    $record = RecordsOwner::factory()->create();
    File::factory()->for($record)->count(2)->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('deleteRecord', $record->id)
        ->assertRedirect(route('admin.records.index'));

    expect(RecordsOwner::query()->count())->toBe(0)
        ->and(File::query()->count())->toBe(0);
});

test('cannot delete a different record from the record page', function () {
    $record = RecordsOwner::factory()->create();
    $other = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('deleteRecord', $other->id)
        ->assertHasNoErrors();

    expect(RecordsOwner::query()->count())->toBe(2);
});

test('deletes a file from a record', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create();
    File::factory()->for($record)->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('deleteFile', $file->id)
        ->assertHasNoErrors()
        ->assertDispatched('modal-saved', 'file-delete');

    expect($record->files()->count())->toBe(1);
});

test('cannot delete a file through the wrong record', function () {
    $record = RecordsOwner::factory()->create();
    $other = RecordsOwner::factory()->create();
    $file = File::factory()->for($other)->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('deleteFile', $file->id);

    expect(File::query()->count())->toBe(1);
});
