<?php

use App\Models\File;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
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

test('cannot edit a file through the wrong record', function () {
    $record = RecordsOwner::factory()->create();
    $other = RecordsOwner::factory()->create();
    $file = File::factory()->for($other)->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditFile', $file->id)
        ->assertNotFound();
});

test('edits a record from the record page', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Old Name']);
    $arbo = OwnerType::factory()->create(['name' => 'ARBO']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditRecord')
        ->assertSet('owner_name', 'Old Name')
        ->set('owner_name', 'New Name')
        ->set('owner_type_id', (string) $arbo->id)
        ->call('saveRecord')
        ->assertHasNoErrors()
        ->assertSet('editingRecord', false)
        ->assertDispatched('modal-saved', 'record-form');

    expect($record->fresh())
        ->owner_name->toBe('New Name')
        ->ownerType->name->toBe('ARBO');
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
