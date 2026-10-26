<?php

use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use Livewire\Livewire;

/**
 * @return array{land: RecordType, lease: RecordType}
 */
function searchFixture(): array
{
    return [
        'land' => RecordType::factory()->create(['name' => 'Land Registry']),
        'lease' => RecordType::factory()->create(['name' => 'Lease Agreements']),
    ];
}

test('searches the owner name', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    Livewire::test('admin::records.index')
        ->set('search', 'lovel')
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('searches the owner name regardless of case', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    Livewire::test('admin::records.index')
        ->set('search', 'GRACE')
        ->assertSee('Grace Hopper')
        ->assertDontSee('Ada Lovelace');
});

test('searches the owner type name', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace', 'owner_type_id' => OwnerType::factory()->create(['name' => 'ARBO'])->id]);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper', 'owner_type_id' => OwnerType::factory()->create(['name' => 'None ARB'])->id]);

    Livewire::test('admin::records.index')
        ->set('search', 'None')
        ->assertSee('Grace Hopper')
        ->assertDontSee('Ada Lovelace');
});

test('searches the record type name', function () {
    ['land' => $land, 'lease' => $lease] = searchFixture();

    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace', 'record_type_id' => $land->id]);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper', 'record_type_id' => $lease->id]);

    Livewire::test('admin::records.index')
        ->set('search', 'lease')
        ->assertSee('Grace Hopper')
        ->assertDontSee('Ada Lovelace');
});

test('treats a blank or whitespace search as no filter', function (string $search) {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    Livewire::test('admin::records.index')
        ->set('search', $search)
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper')
        ->assertSet('isFiltered', false);
})->with([
    'empty' => '',
    'spaces' => '   ',
]);

test('a search matching nothing says so and offers a way back', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    Livewire::test('admin::records.index')
        ->set('search', 'nobody at all')
        ->assertDontSee('Ada Lovelace')
        ->assertSee('No records match your search.')
        ->assertSet('isFiltered', true);
});

test('counts only the matching records', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    RecordsOwner::factory()->create(['owner_name' => 'Ada Byron']);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    Livewire::test('admin::records.index')
        ->set('search', 'Ada')
        ->assertSee('2 records match your filters.')
        ->assertDontSee('3 records on file.');
});

test('filters by record type on its own', function () {
    ['land' => $land, 'lease' => $lease] = searchFixture();

    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace', 'record_type_id' => $land->id]);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper', 'record_type_id' => $lease->id]);

    Livewire::test('admin::records.index')
        ->set('recordType', (string) $lease->id)
        ->assertSee('Grace Hopper')
        ->assertDontSee('Ada Lovelace');
});

test('combines the text search with the record type filter', function () {
    ['land' => $land, 'lease' => $lease] = searchFixture();

    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace', 'record_type_id' => $land->id]);
    RecordsOwner::factory()->create(['owner_name' => 'Ada Byron', 'record_type_id' => $lease->id]);

    Livewire::test('admin::records.index')
        ->set('search', 'Ada')
        ->set('recordType', (string) $land->id)
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Ada Byron');
});

test('offers every record type in the dropdown', function () {
    searchFixture();

    Livewire::test('admin::records.index')
        ->assertSeeHtml('id="recordType"')
        ->assertSeeHtml('id="recordType-listbox"')
        ->assertSee('All record types')
        ->assertSee('Land Registry')
        ->assertSee('Lease Agreements');
});

test('clearing the filters brings every record back', function () {
    ['land' => $land] = searchFixture();

    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace', 'record_type_id' => $land->id]);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    Livewire::test('admin::records.index')
        ->set('search', 'Ada')
        ->set('recordType', (string) $land->id)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('recordType', '')
        ->assertSet('isFiltered', false)
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper');
});

test('a search resets pagination to the first page', function () {
    RecordsOwner::factory()
        ->count(20)
        ->sequence(...array_map(
            fn (int $day) => ['owner_name' => sprintf('Owner %02d', $day), 'created_at' => now()->subDays($day)],
            range(0, 19),
        ))
        ->create();

    Livewire::test('admin::records.index')
        ->set('paginators.page', 2)
        ->assertSee('Owner 16')
        ->set('search', 'Owner 00')
        ->assertSet('paginators.page', 1)
        ->assertSee('Owner 00');
});

test('filters live in the url so a filtered list can be shared', function () {
    ['land' => $land] = searchFixture();

    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace', 'record_type_id' => $land->id]);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    $this->get(route('admin.records.index', ['search' => 'Ada', 'type' => $land->id]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the url cannot be used to break the query', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    $this->get(route('admin.records.index', ['search' => "'; drop table records_owners; --", 'type' => 'not-an-id']))
        ->assertOk()
        // The term matches nothing, and the id is not a number so the filter is ignored.
        ->assertSee('No records match your search.');

    // The injected statement was bound as a value, not executed.
    expect(RecordsOwner::query()->whereKey($record->id)->exists())->toBeTrue();
});

test('a percent sign is searched for literally instead of matching everything', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Fifty % Off Ltd']);
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);

    Livewire::test('admin::records.index')
        ->set('search', '%')
        ->assertSee('Fifty % Off Ltd')
        ->assertDontSee('Ada Lovelace');
});

test('an underscore is searched for literally instead of matching any character', function () {
    // Both rows share the "ARB" owner type, which no escaped "A_B" pattern can
    // match, so only owner_name decides the result.
    $arb = OwnerType::factory()->create(['name' => 'ARB']);

    RecordsOwner::factory()->create(['owner_name' => 'A_B Holdings', 'owner_type_id' => $arb->id]);
    RecordsOwner::factory()->create(['owner_name' => 'AxB Holdings', 'owner_type_id' => $arb->id]);

    Livewire::test('admin::records.index')
        ->set('search', 'A_B')
        ->assertSee('A_B Holdings')
        ->assertDontSee('AxB Holdings');
});

test('a backslash in the search does not break the query', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    Livewire::test('admin::records.index')
        ->set('search', '\\')
        ->assertOk();
});

test('the search box is debounced so typing does not query per keystroke', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('wire:model.live.debounce.300ms="search"', escape: false)
        // The record type filter is a combobox that entangles live, so picking
        // a type re-queries immediately.
        ->assertSee("value: \$wire.\$entangle('recordType', true)", escape: false);
});
