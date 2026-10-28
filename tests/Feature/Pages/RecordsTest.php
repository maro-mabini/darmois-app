<?php

use App\Models\File;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use Illuminate\Support\Str;

function recordRow(string $content, string $ownerName): string
{
    return Str::between(
        (string) preg_replace('/\s+/', ' ', $content),
        '> '.$ownerName.' </td>',
        '</tr>',
    );
}

test('lists the created records with their file count', function () {
    $withFiles = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    File::factory()->for($withFiles)->count(3)->create();
    $withoutFiles = RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    $response = $this->get(route('admin.records.index'));

    $response->assertOk()->assertSee('Ada Lovelace')->assertSee('Grace Hopper');

    $content = $response->getContent();

    expect(recordRow($content, 'Ada Lovelace'))->toContain('> 3 </td>')
        ->and(recordRow($content, 'Grace Hopper'))->toContain('> 0 </td>');
});

test('each record row carries the files that belong to it', function () {
    $record = RecordsOwner::factory()->create(['owner_name' => 'Ada Lovelace']);
    File::factory()->for($record)->create([
        'file_name' => 'Deed of transfer.pdf',
        'file_description' => 'Signed transfer deed',
        'date_of_file' => '1893-04-12',
    ]);

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('Files on this record')
        ->assertSee('Deed of transfer.pdf')
        ->assertSee('Signed transfer deed')
        ->assertSee('Apr 12, 1893')
        ->assertSee('id="record-files-'.$record->id.'"', escape: false);
});

test('a record without files says so in its expanded panel', function () {
    RecordsOwner::factory()->create(['owner_name' => 'Grace Hopper']);

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('No files on this record yet.');
});

test('renders the record type and owner type of each record', function () {
    RecordsOwner::factory()->create([
        'owner_name' => 'Alan Turing',
        'owner_type_id' => OwnerType::factory()->create(['name' => 'ARBO'])->id,
        'record_type_id' => RecordType::factory()->create(['name' => 'Land Registry'])->id,
    ]);

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('Alan Turing')
        ->assertSee('ARBO')
        ->assertSee('Land Registry');
});

test('shows an empty state when no record exists', function () {
    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('No records yet.')
        ->assertDontSee('aria-label="Pagination Navigation"', escape: false);
});

test('paginates the records fifteen to a page', function () {
    RecordsOwner::factory()
        ->count(16)
        ->sequence(...array_map(
            fn (int $day) => ['owner_name' => sprintf('Owner %02d', $day), 'created_at' => now()->subDays($day)],
            range(0, 15),
        ))
        ->create();

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee('Owner 00')
        ->assertDontSee('Owner 15')
        ->assertSee('aria-label="Pagination Navigation"', escape: false);

    $this->get(route('admin.records.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('Owner 15');
});

test('links to a record with wire:navigate instead of calling navigate on the component', function () {
    $record = RecordsOwner::factory()->create();

    $this->get(route('admin.records.index'))
        ->assertOk()
        ->assertSee(route('admin.records.show', $record), escape: false)
        ->assertSee('wire:navigate', escape: false)
        ->assertDontSee('$wire.navigate', escape: false);
});
