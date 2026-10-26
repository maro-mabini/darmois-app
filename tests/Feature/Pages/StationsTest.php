<?php

use App\Models\RecordsOwner;
use App\Models\Station;
use Livewire\Livewire;

test('lists the stations with the records each handles', function () {
    Station::factory()->create(['name' => 'DAR Station I']);
    $inUse = Station::factory()->create(['name' => 'DAR Station II']);
    RecordsOwner::factory()->count(2)->create(['station_id' => $inUse->id]);

    Livewire::test('admin::stations.index')
        ->assertOk()
        ->assertSee('DAR Station I')
        ->assertSee('DAR Station II')
        ->assertSee('2');
});

test('creates a station with a description', function () {
    Livewire::test('admin::stations.index')
        ->call('startCreate')
        ->set('name', 'DAR Station I')
        ->set('description', 'Covers the northern district.')
        ->call('save')
        ->assertHasNoErrors();

    expect(Station::query()->where('name', 'DAR Station I')->first())
        ->description->toBe('Covers the northern district.');
});

test('a station description is optional', function () {
    Livewire::test('admin::stations.index')
        ->call('startCreate')
        ->set('name', 'DAR Station I')
        ->set('description', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Station::query()->first()->description)->toBeNull();
});

test('requires a unique station name', function () {
    Station::factory()->create(['name' => 'DAR Station I']);

    Livewire::test('admin::stations.index')
        ->call('startCreate')
        ->set('name', 'DAR Station I')
        ->call('save')
        ->assertHasErrors('name');

    expect(Station::query()->count())->toBe(1);
});

test('requires a station name', function () {
    Livewire::test('admin::stations.index')
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors('name');

    expect(Station::query()->count())->toBe(0);
});

test('edits a station', function () {
    $station = Station::factory()->create(['name' => 'Old Name', 'description' => 'Old text']);

    Livewire::test('admin::stations.index')
        ->call('startEdit', $station->id)
        ->assertSet('name', 'Old Name')
        ->assertSet('description', 'Old text')
        ->set('name', 'New Name')
        ->set('description', 'New text')
        ->call('save')
        ->assertHasNoErrors();

    expect($station->fresh())
        ->name->toBe('New Name')
        ->description->toBe('New text');
});

test('renaming a station leaves its own name available', function () {
    $station = Station::factory()->create(['name' => 'DAR Station I']);

    Livewire::test('admin::stations.index')
        ->call('startEdit', $station->id)
        ->set('name', 'DAR Station I')
        ->call('save')
        ->assertHasNoErrors();

    expect($station->fresh()->name)->toBe('DAR Station I');
});

test('rejects renaming a station onto an existing name', function () {
    Station::factory()->create(['name' => 'DAR Station II']);
    $station = Station::factory()->create(['name' => 'DAR Station I']);

    Livewire::test('admin::stations.index')
        ->call('startEdit', $station->id)
        ->set('name', 'DAR Station II')
        ->call('save')
        ->assertHasErrors('name');

    expect($station->fresh()->name)->toBe('DAR Station I');
});

test('deletes a station that no record points at', function () {
    $station = Station::factory()->create();

    Livewire::test('admin::stations.index')
        ->call('delete', $station->id)
        ->assertHasNoErrors();

    expect(Station::query()->count())->toBe(0);
});

test('refuses to delete a station in use', function () {
    $station = Station::factory()->create();
    RecordsOwner::factory()->count(2)->create(['station_id' => $station->id]);

    Livewire::test('admin::stations.index')
        ->call('delete', $station->id)
        ->assertHasErrors('stations');

    expect(Station::query()->count())->toBe(1);
});
