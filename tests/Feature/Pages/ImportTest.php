<?php

use App\Models\File;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Support\SqlImport\DatabaseImporter;
use App\Support\SqlImport\SqlDump;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * A pg_dump-shaped file: DDL up front, one COPY block per table, and the psql
 * noise the importer is expected to ignore.
 *
 * @param  array<string, array{columns: list<string>, rows: list<string>}>  $datasets
 */
function pgDumpForImport(array $datasets): string
{
    $lines = [
        '--',
        '-- PostgreSQL database dump',
        '--',
        'SET statement_timeout = 0;',
        "SET client_encoding = 'UTF8';",
        '',
    ];

    foreach ($datasets as $table => $dataset) {
        $lines[] = "CREATE TABLE public.{$table} ();";
        $lines[] = "ALTER TABLE public.{$table} OWNER TO postgres;";
        $lines[] = 'COPY public.'.$table.' ('.implode(', ', $dataset['columns']).') FROM stdin;';
        array_push($lines, ...$dataset['rows']);
        $lines[] = '\\.';
    }

    return implode("\n", $lines)."\n";
}

/**
 * One COPY line, with nulls written the way pg_dump writes them.
 *
 * @param  list<string|int|null>  $values
 */
function copyRow(array $values): string
{
    return implode("\t", array_map(
        fn (string|int|null $value): string => $value === null ? '\\N' : (string) $value,
        $values,
    ));
}

/**
 * Read a dump straight from a string, without going through an upload.
 */
function importDump(string $sql): SqlDump
{
    $path = tempnam(sys_get_temp_dir(), 'darmois-dump-');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary file.');
    }

    file_put_contents($path, $sql);

    try {
        return SqlDump::fromPath($path);
    } finally {
        unlink($path);
    }
}

test('the import page renders through the router', function () {
    $this->get(route('admin.import.index'))
        ->assertOk()
        ->assertSee('Import')
        ->assertSee('Choose a .sql file');
});

test('the import page is offered in the navigation', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.import.index'), escape: false);
});

test('the import page does not exist in production', function () {
    $this->app['env'] = 'production';

    $this->get(route('admin.import.index'))->assertNotFound();
});

test('reads an uploaded dump and reports what it holds', function () {
    Livewire::test('admin::import.index')
        ->set('file', UploadedFile::fake()->createWithContent('dump.sql', pgDumpForImport([
            'record_types' => [
                'columns' => ['id', 'name', 'created_at', 'updated_at'],
                'rows' => [copyRow([1, 'Case File', '2026-01-01 00:00:00', '2026-01-01 00:00:00'])],
            ],
        ])))
        ->assertHasNoErrors()
        ->assertSee('PostgreSQL dump')
        ->assertSee('record_types');
});

test('rejects a file that is not sql', function () {
    Livewire::test('admin::import.index')
        ->set('file', UploadedFile::fake()->createWithContent('notes.pdf', 'not sql'))
        ->assertHasErrors('file');
});

test('imports an uploaded dump through the page', function () {
    $component = Livewire::test('admin::import.index')
        ->set('file', UploadedFile::fake()->createWithContent('dump.sql', pgDumpForImport([
            'record_types' => [
                'columns' => ['id', 'name', 'created_at', 'updated_at'],
                'rows' => [copyRow([1, 'Case File', '2026-01-01 00:00:00', '2026-01-01 00:00:00'])],
            ],
        ])))
        ->set('mode', DatabaseImporter::REPLACE)
        ->call('import')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: '1 row imported.');

    expect(RecordType::query()->pluck('name')->all())->toBe(['Case File'])
        ->and($component->get('report')['dialect'])->toBe(SqlDump::POSTGRES);
});

test('replaces the covered tables with the rows in a postgres dump', function () {
    $ownerType = OwnerType::factory()->create();
    $oldType = RecordType::factory()->create(['name' => 'Old Type']);
    $oldOwner = RecordsOwner::factory()->create([
        'owner_type_id' => $ownerType->id,
        'record_type_id' => $oldType->id,
    ]);

    $dump = importDump(pgDumpForImport([
        'record_types' => [
            'columns' => ['id', 'name', 'created_at', 'updated_at'],
            'rows' => [
                copyRow([1, 'Case File', '2026-01-01 00:00:00', '2026-01-01 00:00:00']),
                copyRow([2, 'Profile', '2026-01-01 00:00:00', '2026-01-01 00:00:00']),
            ],
        ],
        'records_owners' => [
            'columns' => [
                'id', 'uuid', 'owner_name', 'location_id', 'station_id',
                'owner_type_id', 'record_type_id', 'created_at', 'updated_at',
            ],
            'rows' => [
                copyRow([
                    7, (string) Str::uuid(), 'Ada Lovelace', null, null,
                    $ownerType->id, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00',
                ]),
            ],
        ],
    ]));

    $report = app(DatabaseImporter::class)->import($dump, DatabaseImporter::REPLACE);

    expect(RecordType::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['Case File', 'Profile'])
        ->and(RecordsOwner::query()->whereKey($oldOwner->id)->exists())->toBeFalse()
        ->and(RecordsOwner::query()->sole()->owner_name)->toBe('Ada Lovelace')
        ->and(RecordsOwner::query()->sole()->record_type_id)->toBe(1)
        ->and($report['mode'])->toBe('replace')
        ->and($report['backup'])->toBeNull()
        ->and($report['dangling'])->toBe([]);
});

test('appends rows with fresh ids and moves their foreign keys along', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'ARB']);
    $existingType = RecordType::factory()->create(['name' => 'Existing Type']);

    $dump = importDump(pgDumpForImport([
        'record_types' => [
            'columns' => ['id', 'name', 'created_at', 'updated_at'],
            'rows' => [copyRow([3, 'Dumped Type', '2026-01-01 00:00:00', '2026-01-01 00:00:00'])],
        ],
        'records_owners' => [
            'columns' => [
                'id', 'uuid', 'owner_name', 'location_id', 'station_id',
                'owner_type_id', 'record_type_id', 'created_at', 'updated_at',
            ],
            'rows' => [
                copyRow([
                    5, (string) Str::uuid(), 'Ada Lovelace', null, null,
                    $ownerType->id, 3, '2026-01-01 00:00:00', '2026-01-01 00:00:00',
                ]),
            ],
        ],
        'files' => [
            'columns' => [
                'id', 'records_owners_id', 'file_name', 'file_description',
                'date_of_file', 'created_at', 'updated_at',
            ],
            'rows' => [
                copyRow([9, 5, 'title.pdf', null, null, '2026-01-01 00:00:00', '2026-01-01 00:00:00']),
            ],
        ],
    ]));

    $report = app(DatabaseImporter::class)->import($dump, DatabaseImporter::APPEND);

    $type = RecordType::query()->where('name', 'Dumped Type')->sole();
    $owner = RecordsOwner::query()->where('owner_name', 'Ada Lovelace')->sole();
    $file = File::query()->where('file_name', 'title.pdf')->sole();

    expect(RecordType::query()->count())->toBe(2)
        ->and($type->id)->toBe($existingType->id + 1)
        ->and($owner->id)->not->toBe(5)
        ->and($owner->owner_type_id)->toBe($ownerType->id)
        ->and($owner->record_type_id)->toBe($type->id)
        ->and($file->id)->not->toBe(9)
        ->and($file->records_owners_id)->toBe($owner->id)
        ->and($report['dangling'])->toBe([]);
});

test('skips the tables of a dump that the app does not have', function () {
    $dump = importDump(pgDumpForImport([
        'record_types' => [
            'columns' => ['id', 'name', 'created_at', 'updated_at'],
            'rows' => [copyRow([1, 'Case File', '2026-01-01 00:00:00', '2026-01-01 00:00:00'])],
        ],
        'legacy_notes' => [
            'columns' => ['id', 'note'],
            'rows' => [copyRow([1, 'Left behind'])],
        ],
    ]));

    $report = app(DatabaseImporter::class)->import($dump, DatabaseImporter::REPLACE);

    $legacy = collect($report['tables'])->firstWhere('table', 'legacy_notes');
    $known = collect($report['tables'])->firstWhere('table', 'record_types');

    expect($legacy)->not->toBeNull()
        ->and($legacy['skipped'])->toBeTrue()
        ->and($legacy['rows'])->toBe(0)
        ->and($known['skipped'])->toBeFalse()
        ->and($known['rows'])->toBe(1);
});

test('runs a plain sql file as written', function () {
    RecordType::factory()->create(['name' => 'Keep Me']);

    $dump = importDump(implode("\n", [
        "INSERT INTO record_types (id, name, created_at, updated_at) VALUES (10, 'Case File', '2026-01-01 00:00:00', '2026-01-01 00:00:00');",
        "INSERT INTO record_types (id, name, created_at, updated_at) VALUES (11, 'Profile', '2026-01-01 00:00:00', '2026-01-01 00:00:00');",
    ]));

    $report = app(DatabaseImporter::class)->import($dump, DatabaseImporter::APPEND);

    expect($dump->dialect)->toBe(SqlDump::PLAIN)
        ->and(RecordType::query()->count())->toBe(3)
        ->and($report['statements'])->toBe(2)
        ->and($report['tables'][0]['table'])->toBe('record_types')
        ->and($report['tables'][0]['rows'])->toBe(2)
        ->and($report['dangling'])->toBe([]);
});

test('empties the insert targets of a plain sql file before running it', function () {
    RecordType::factory()->create(['name' => 'Keep Me']);

    $dump = importDump("INSERT INTO record_types (id, name, created_at, updated_at) VALUES (10, 'Only Row', '2026-01-01 00:00:00', '2026-01-01 00:00:00');");

    app(DatabaseImporter::class)->import($dump, DatabaseImporter::REPLACE);

    expect(RecordType::query()->pluck('name')->all())->toBe(['Only Row']);
});

test('creates and fills a table a plain sql file brings with it', function () {
    $dump = importDump(implode("\n", [
        'CREATE TABLE legacy_notes (id INTEGER PRIMARY KEY, note TEXT NOT NULL);',
        "INSERT INTO legacy_notes (id, note) VALUES (1, 'Kept');",
    ]));

    $report = app(DatabaseImporter::class)->import($dump, DatabaseImporter::APPEND);

    $entry = collect($report['tables'])->firstWhere('table', 'legacy_notes');

    expect(DB::table('legacy_notes')->pluck('note')->all())->toBe(['Kept'])
        ->and($entry)->not->toBeNull()
        ->and($entry['skipped'])->toBeFalse()
        ->and($entry['rows'])->toBe(1)
        ->and($report['statements'])->toBe(2);
});

test('reports how many statements a plain sql file ran', function () {
    RecordType::factory()->create(['name' => 'Keep Me']);

    Livewire::test('admin::import.index')
        ->set('file', UploadedFile::fake()->createWithContent(
            'noop.sql',
            "UPDATE record_types SET name = 'Keep Me' WHERE id = 0;",
        ))
        ->call('import')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: '1 statement run.');

    expect(RecordType::query()->count())->toBe(1);
});

test('puts the database back when a load fails halfway', function () {
    RecordType::factory()->create(['name' => 'Existing Type']);

    $dump = importDump(pgDumpForImport([
        'record_types' => [
            'columns' => ['id', 'name', 'created_at', 'updated_at'],
            'rows' => [
                copyRow([3, 'Fresh Type', '2026-01-01 00:00:00', '2026-01-01 00:00:00']),
                copyRow([4, 'Existing Type', '2026-01-01 00:00:00', '2026-01-01 00:00:00']),
            ],
        ],
    ]));

    expect(fn () => app(DatabaseImporter::class)->import($dump, DatabaseImporter::APPEND))
        ->toThrow(RuntimeException::class);

    expect(RecordType::query()->pluck('name')->all())->toBe(['Existing Type']);
});
