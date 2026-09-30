<?php

use App\Models\RecordsOwner;
use App\Models\RecordType;
use Livewire\Livewire;

test('lists the record types with the records using each', function () {
    RecordType::factory()->create(['name' => 'Land Registry']);
    $inUse = RecordType::factory()->create(['name' => 'Case File']);
    RecordsOwner::factory()->count(2)->create(['record_type_id' => $inUse->id]);

    Livewire::test('admin::record-types.index')
        ->assertOk()
        ->assertSee('Land Registry')
        ->assertSee('Case File');
});

test('creates a record type', function () {
    Livewire::test('admin::record-types.index')
        ->call('startCreate')
        ->set('name', 'Land Registry')
        ->call('save')
        ->assertHasNoErrors();

    expect(RecordType::query()->where('name', 'Land Registry')->exists())->toBeTrue();
});

test('requires a unique record type name', function () {
    RecordType::factory()->create(['name' => 'Case File']);

    Livewire::test('admin::record-types.index')
        ->call('startCreate')
        ->set('name', 'Case File')
        ->call('save')
        ->assertHasErrors('name');

    expect(RecordType::query()->where('name', 'Case File')->count())->toBe(1);
});

test('requires a record type name', function () {
    Livewire::test('admin::record-types.index')
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors('name');

    expect(RecordType::query()->where('name', '')->exists())->toBeFalse();
});

test('edits a record type', function () {
    $recordType = RecordType::factory()->create(['name' => 'Old Name']);

    Livewire::test('admin::record-types.index')
        ->call('startEdit', $recordType->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($recordType->fresh()->name)->toBe('New Name');
});

test('renaming a record type leaves its own name available', function () {
    $recordType = RecordType::factory()->create(['name' => 'Case File']);

    Livewire::test('admin::record-types.index')
        ->call('startEdit', $recordType->id)
        ->set('name', 'Case File')
        ->call('save')
        ->assertHasNoErrors();

    expect($recordType->fresh()->name)->toBe('Case File');
});

test('rejects renaming a record type onto an existing name', function () {
    RecordType::factory()->create(['name' => 'Profile']);
    $recordType = RecordType::factory()->create(['name' => 'Case File']);

    Livewire::test('admin::record-types.index')
        ->call('startEdit', $recordType->id)
        ->set('name', 'Profile')
        ->call('save')
        ->assertHasErrors('name');

    expect($recordType->fresh()->name)->toBe('Case File');
});

test('deletes a record type that no record uses', function () {
    $recordType = RecordType::factory()->create();

    Livewire::test('admin::record-types.index')
        ->call('delete', $recordType->id)
        ->assertHasNoErrors();

    expect(RecordType::query()->count())->toBe(0);
});

test('refuses to delete a record type in use', function () {
    $recordType = RecordType::factory()->create();
    RecordsOwner::factory()->count(2)->create(['record_type_id' => $recordType->id]);

    Livewire::test('admin::record-types.index')
        ->call('delete', $recordType->id)
        ->assertHasErrors('recordTypes');

    expect(RecordType::query()->count())->toBe(1);
});
