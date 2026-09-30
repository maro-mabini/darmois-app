<?php

use App\Models\OwnerType;
use App\Models\RecordsOwner;
use Livewire\Livewire;

test('lists the owner types with the records using each', function () {
    OwnerType::factory()->create(['name' => 'ARB']);
    $inUse = OwnerType::factory()->create(['name' => 'None ARB']);
    RecordsOwner::factory()->count(2)->create(['owner_type_id' => $inUse->id]);

    Livewire::test('admin::owner-types.index')
        ->assertOk()
        ->assertSee('ARB')
        ->assertSee('None ARB');
});

test('creates an owner type', function () {
    Livewire::test('admin::owner-types.index')
        ->call('startCreate')
        ->set('name', 'Trust')
        ->call('save')
        ->assertHasNoErrors();

    expect(OwnerType::query()->where('name', 'Trust')->exists())->toBeTrue();
});

test('requires a unique owner type name', function () {
    OwnerType::factory()->create(['name' => 'ARB']);

    Livewire::test('admin::owner-types.index')
        ->call('startCreate')
        ->set('name', 'ARB')
        ->call('save')
        ->assertHasErrors('name');

    expect(OwnerType::query()->where('name', 'ARB')->count())->toBe(1);
});

test('requires an owner type name', function () {
    Livewire::test('admin::owner-types.index')
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors('name');

    expect(OwnerType::query()->count())->toBe(0);
});

test('edits an owner type', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'Old Name']);

    Livewire::test('admin::owner-types.index')
        ->call('startEdit', $ownerType->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($ownerType->fresh()->name)->toBe('New Name');
});

test('renaming an owner type leaves its own name available', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'Trust']);

    Livewire::test('admin::owner-types.index')
        ->call('startEdit', $ownerType->id)
        ->set('name', 'Trust')
        ->call('save')
        ->assertHasNoErrors();

    expect($ownerType->fresh()->name)->toBe('Trust');
});

test('rejects renaming an owner type onto an existing name', function () {
    OwnerType::factory()->create(['name' => 'ARB']);
    $ownerType = OwnerType::factory()->create(['name' => 'Trust']);

    Livewire::test('admin::owner-types.index')
        ->call('startEdit', $ownerType->id)
        ->set('name', 'ARB')
        ->call('save')
        ->assertHasErrors('name');

    expect($ownerType->fresh()->name)->toBe('Trust');
});

test('saving closes the form and clears the editing target', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'ARB']);

    Livewire::test('admin::owner-types.index')
        ->call('startEdit', $ownerType->id)
        ->set('name', 'ARBO')
        ->call('save')
        ->assertSet('editing', null)
        ->assertSet('name', '')
        ->assertDispatched('modal-saved', 'owner-type-form');
});

test('a failed save reopens the form so the input is not lost', function () {
    Livewire::test('admin::owner-types.index')
        ->call('startCreate')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name')
        ->assertDispatched('modal-open', 'owner-type-form');
});

test('starting a create after a failed edit clears the previous errors', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'ARB']);

    Livewire::test('admin::owner-types.index')
        ->call('startEdit', $ownerType->id)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name')
        ->call('startCreate')
        ->assertSet('name', '')
        ->assertSet('editing', null)
        ->assertHasNoErrors();
});

test('deletes an owner type that no record uses', function () {
    $ownerType = OwnerType::factory()->create();

    Livewire::test('admin::owner-types.index')
        ->call('delete', $ownerType->id)
        ->assertHasNoErrors()
        ->assertDispatched('modal-saved', 'owner-type-delete');

    expect(OwnerType::query()->count())->toBe(0);
});

test('refuses to delete an owner type in use', function () {
    $ownerType = OwnerType::factory()->create();
    RecordsOwner::factory()->count(2)->create(['owner_type_id' => $ownerType->id]);

    Livewire::test('admin::owner-types.index')
        ->call('delete', $ownerType->id)
        ->assertHasErrors('ownerTypes')
        // The count has to reach the user, not just the fact that it failed.
        ->assertSee('is used by 2 records and cannot be deleted.')
        ->assertNotDispatched('modal-saved', 'owner-type-delete')
        // The dialog has to survive the re-render, or the reason vanishes.
        ->assertDispatched('modal-open');

    expect(OwnerType::query()->count())->toBe(1);
});

test('deleting an owner type leaves its records alone', function () {
    $ownerType = OwnerType::factory()->create();
    $usedByAnother = OwnerType::factory()->create();
    $record = RecordsOwner::factory()->create(['owner_type_id' => $ownerType->id]);

    Livewire::test('admin::owner-types.index')
        ->call('delete', $usedByAnother->id)
        ->assertHasNoErrors();

    expect($record->fresh()->owner_type_id)->toBe($ownerType->id);
});

test('the owner type index renders both modals over http', function () {
    OwnerType::factory()->create(['name' => 'ARB']);

    $this->get(route('admin.owner-types.index'))
        ->assertOk()
        ->assertSee('ARB')
        ->assertSee('<dialog', escape: false)
        ->assertSee('New owner type')
        ->assertSee('Delete owner type?');
});

test('the owner type form and delete pages are gone', function () {
    $ownerType = OwnerType::factory()->create();

    $this->get('/owner-types/create')->assertNotFound();
    $this->get("/owner-types/{$ownerType->id}/edit")->assertNotFound();
});
