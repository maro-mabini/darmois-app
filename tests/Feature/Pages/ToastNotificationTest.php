<?php

use App\Models\File;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use Livewire\Livewire;

test('the layout renders a notification region on every page', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('aria-label="Notifications"', escape: false)
        ->assertSee('x-on:toast.window', escape: false);
})->with([
    'dashboard' => 'admin.dashboard',
    'records' => 'admin.records.index',
    'record types' => 'admin.record-types.index',
    'owner types' => 'admin.owner-types.index',
]);

test('the notification region survives a navigation by keeping its queue in session storage', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('darmois:toasts', escape: false);
});

test('a notification message is rendered as text rather than markup', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('x-text="item.message"', escape: false)
        ->assertDontSee('x-html="item.message"', escape: false);
});

test('creating an owner type notifies', function () {
    Livewire::test('admin::owner-types.index')
        ->call('startCreate')
        ->set('name', 'Trust')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Owner type created.');
});

test('editing an owner type notifies', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'Old Name']);

    Livewire::test('admin::owner-types.index')
        ->call('startEdit', $ownerType->id)
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Owner type updated.');
});

test('deleting an owner type names the type that went', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'Trust']);

    Livewire::test('admin::owner-types.index')
        ->call('delete', $ownerType->id)
        ->assertDispatched('toast', message: 'Owner type “Trust” deleted.');
});

test('a refused owner type delete does not notify', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'ARB']);
    RecordsOwner::factory()->create(['owner_type_id' => $ownerType->id]);

    Livewire::test('admin::owner-types.index')
        ->call('delete', $ownerType->id)
        ->assertHasErrors('ownerTypes')
        ->assertNotDispatched('toast');

    expect(OwnerType::query()->count())->toBe(1);
});

test('a rejected owner type save does not notify', function () {
    Livewire::test('admin::owner-types.index')
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors('name')
        ->assertNotDispatched('toast');
});

test('creating a record type notifies', function () {
    Livewire::test('admin::record-types.index')
        ->call('startCreate')
        ->set('name', 'Land Registry')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Record type created.');
});

test('editing a record type notifies', function () {
    $recordType = RecordType::factory()->create(['name' => 'Old Name']);

    Livewire::test('admin::record-types.index')
        ->call('startEdit', $recordType->id)
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Record type updated.');
});

test('deleting a record type names the type that went', function () {
    $recordType = RecordType::factory()->create(['name' => 'Land Registry']);

    Livewire::test('admin::record-types.index')
        ->call('delete', $recordType->id)
        ->assertDispatched('toast', message: 'Record type “Land Registry” deleted.');
});

test('a refused record type delete does not notify', function () {
    $recordType = RecordType::factory()->create(['name' => 'Land Registry']);
    RecordsOwner::factory()->create(['record_type_id' => $recordType->id]);

    Livewire::test('admin::record-types.index')
        ->call('delete', $recordType->id)
        ->assertHasErrors('recordTypes')
        ->assertNotDispatched('toast');

    expect(RecordType::query()->count())->toBe(1);
});

test('creating a record notifies', function () {
    $ownerType = OwnerType::factory()->create();
    $recordType = RecordType::factory()->create();

    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', 'Ada Lovelace')
        ->set('owner_type_id', (string) $ownerType->id)
        ->set('record_type_id', (string) $recordType->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Record created.');
});

test('editing a record notifies', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    Livewire::test('admin::records.index')
        ->call('startEdit', $record->id)
        ->set('owner_name', 'Grace Hopper')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Record updated.');
});

test('deleting a record from the list names the record that went', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    Livewire::test('admin::records.index')
        ->call('delete', $record->id)
        ->assertDispatched('toast', message: 'Record “Ada Lovelace” deleted.');
});

test('a rejected record save does not notify', function () {
    Livewire::test('admin::records.index')
        ->call('startCreate')
        ->set('owner_name', '')
        ->call('save')
        ->assertHasErrors('owner_name')
        ->assertNotDispatched('toast');
});

test('editing a record from its own page notifies', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditRecord')
        ->set('owner_name', 'Grace Hopper')
        ->call('saveRecord')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Record updated.');
});

test('adding a file notifies', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', 'Deed of transfer.pdf')
        ->call('saveFile')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'File added.');
});

test('editing a file notifies', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create(['file_name' => 'Deed of transfer.pdf']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startEditFile', $file->id)
        ->set('file_name', 'Amended deed.pdf')
        ->call('saveFile')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'File updated.');
});

test('deleting a file names the file that went', function () {
    $record = RecordsOwner::factory()->create();
    $file = File::factory()->for($record)->create(['file_name' => 'Deed of transfer.pdf']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('deleteFile', $file->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'File “Deed of transfer.pdf” deleted.');
});

test('a rejected file save does not notify', function () {
    $record = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('startCreateFile')
        ->set('file_name', '')
        ->call('saveFile')
        ->assertHasErrors('file_name')
        ->assertNotDispatched('toast');
});

test('deleting a record from its own page notifies before it redirects', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('deleteRecord', $record->id)
        ->assertDispatched('toast', message: 'Record “Ada Lovelace” deleted.')
        ->assertRedirect(route('admin.records.index'));
});

test('an id that does not match the record on screen notifies nothing and redirects nowhere', function () {
    $record = RecordsOwner::factory()->create();
    $other = RecordsOwner::factory()->create();

    Livewire::test('admin::records.show', ['record' => $record])
        ->call('deleteRecord', $other->id)
        ->assertNotDispatched('toast');

    expect(RecordsOwner::query()->count())->toBe(2);
});
