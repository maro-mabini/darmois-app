<?php

use App\Models\Location;
use App\Models\RecordsOwner;
use Livewire\Livewire;

test('lists the locations with the records filed at each', function () {
    Location::factory()->create(['name' => 'Barangay San Isidro']);
    $inUse = Location::factory()->create(['name' => 'Barangay Poblacion']);
    RecordsOwner::factory()->count(2)->create(['location_id' => $inUse->id]);

    Livewire::test('admin::locations.index')
        ->assertOk()
        ->assertSee('Barangay San Isidro')
        ->assertSee('Barangay Poblacion')
        ->assertSee('2');
});

test('creates a location with a description', function () {
    Livewire::test('admin::locations.index')
        ->call('startCreate')
        ->set('name', 'Barangay San Isidro')
        ->set('description', 'The riverside barangay.')
        ->call('save')
        ->assertHasNoErrors();

    expect(Location::query()->where('name', 'Barangay San Isidro')->first())
        ->description->toBe('The riverside barangay.');
});

test('a location description is optional', function () {
    Livewire::test('admin::locations.index')
        ->call('startCreate')
        ->set('name', 'Barangay San Isidro')
        ->set('description', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Location::query()->first()->description)->toBeNull();
});

test('requires a unique location name', function () {
    Location::factory()->create(['name' => 'Barangay San Isidro']);

    Livewire::test('admin::locations.index')
        ->call('startCreate')
        ->set('name', 'Barangay San Isidro')
        ->call('save')
        ->assertHasErrors('name');

    expect(Location::query()->count())->toBe(1);
});

test('requires a location name', function () {
    Livewire::test('admin::locations.index')
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors('name');

    expect(Location::query()->count())->toBe(0);
});

test('edits a location', function () {
    $location = Location::factory()->create(['name' => 'Old Name', 'description' => 'Old text']);

    Livewire::test('admin::locations.index')
        ->call('startEdit', $location->id)
        ->assertSet('name', 'Old Name')
        ->assertSet('description', 'Old text')
        ->set('name', 'New Name')
        ->set('description', 'New text')
        ->call('save')
        ->assertHasNoErrors();

    expect($location->fresh())
        ->name->toBe('New Name')
        ->description->toBe('New text');
});

test('renaming a location leaves its own name available', function () {
    $location = Location::factory()->create(['name' => 'Barangay San Isidro']);

    Livewire::test('admin::locations.index')
        ->call('startEdit', $location->id)
        ->set('name', 'Barangay San Isidro')
        ->call('save')
        ->assertHasNoErrors();

    expect($location->fresh()->name)->toBe('Barangay San Isidro');
});

test('rejects renaming a location onto an existing name', function () {
    Location::factory()->create(['name' => 'Barangay Poblacion']);
    $location = Location::factory()->create(['name' => 'Barangay San Isidro']);

    Livewire::test('admin::locations.index')
        ->call('startEdit', $location->id)
        ->set('name', 'Barangay Poblacion')
        ->call('save')
        ->assertHasErrors('name');

    expect($location->fresh()->name)->toBe('Barangay San Isidro');
});

test('deletes a location that no record is filed at', function () {
    $location = Location::factory()->create();

    Livewire::test('admin::locations.index')
        ->call('delete', $location->id)
        ->assertHasNoErrors();

    expect(Location::query()->count())->toBe(0);
});

test('refuses to delete a location in use', function () {
    $location = Location::factory()->create();
    RecordsOwner::factory()->count(2)->create(['location_id' => $location->id]);

    Livewire::test('admin::locations.index')
        ->call('delete', $location->id)
        ->assertHasErrors('locations');

    expect(Location::query()->count())->toBe(1);
});
