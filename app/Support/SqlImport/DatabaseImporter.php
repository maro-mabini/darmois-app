<?php

namespace App\Support\SqlImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Puts the contents of a SqlDump into the application's own tables.
 *
 * Two jobs, depending on the mode:
 *
 *  - replace empties the tables the file covers and loads the file's rows with
 *    their original ids, so the database ends up exactly as the file says.
 *  - append leaves the existing rows alone and inserts the file's rows after
 *    them with fresh ids, translating the foreign keys as it goes so a row that
 *    pointed at id 3 still points at the same parent, now id 19.
 *
 * Foreign keys are switched off for the duration of the load. A dump is ordered
 * by table, not by dependency, and half a restore that dies on table order is
 * worse than one that runs; the references are checked again afterwards and any
 * dangling ones are reported.
 */
final class DatabaseImporter
{
    public const REPLACE = 'replace';

    public const APPEND = 'append';

    /**
     * The table that records which migrations THIS database has run.
     *
     * A dump carries the other server's history, which is a different list on
     * a different filesystem. Loading it over the local one either erases
     * migrations this database has already applied (the next `migrate` then
     * tries to recreate their tables) or duplicates every name, so the file's
     * copy is reported and left out of the load.
     */
    public const LEDGER = 'migrations';

    /**
     * @return array{
     *     dialect: string,
     *     mode: string,
     *     backup: string|null,
     *     tables: list<array{table: string, rows: int, skipped: bool, note?: string}>,
     *     statements: int,
     *     dangling: list<string>
     * }
     */
    public function import(SqlDump $dump, string $mode): array
    {
        if (! in_array($mode, [self::REPLACE, self::APPEND], true)) {
            throw new RuntimeException("Unknown import mode [{$mode}].");
        }

        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! $sqlite && $dump->dialect === SqlDump::POSTGRES) {
            throw new RuntimeException('A PostgreSQL dump can only be translated into a SQLite database.');
        }

        $foreignKeys = $sqlite && $this->foreignKeysEnabled();
        $backup = $mode === self::REPLACE ? $this->backUp() : null;

        $tables = [];
        $statements = 0;
        $dangling = [];

        if ($sqlite) {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            DB::transaction(function () use ($dump, $mode, $sqlite, &$tables, &$statements): void {
                if ($dump->dialect === SqlDump::POSTGRES) {
                    $tables = $this->importDatasets($dump, $mode, $sqlite);
                } else {
                    [$tables, $statements] = $this->runStatements($dump, $mode);
                }
            });
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        } finally {
            if ($sqlite) {
                DB::statement('PRAGMA foreign_keys = '.($foreignKeys ? 'ON' : 'OFF'));
            }
        }

        if ($sqlite) {
            $dangling = $this->danglingReferences();
        }

        return [
            'dialect' => $dump->dialect,
            'mode' => $mode,
            'backup' => $backup,
            'tables' => $tables,
            'statements' => $statements,
            'dangling' => $dangling,
        ];
    }

    /**
     * @return list<array{table: string, rows: int, skipped: bool, note?: string}>
     */
    private function importDatasets(SqlDump $dump, string $mode, bool $sqlite): array
    {
        $existing = $this->existingTables();
        $tables = [];
        $targets = [];

        foreach (array_keys($dump->datasets) as $table) {
            if ($table === self::LEDGER && in_array($table, $existing, true)) {
                $tables[] = ['table' => $table, 'rows' => 0, 'skipped' => true, 'note' => 'Migration history kept'];

                continue;
            }

            if (in_array($table, $existing, true)) {
                $targets[] = $table;

                continue;
            }

            $tables[] = ['table' => $table, 'rows' => 0, 'skipped' => true];
        }

        $ordered = $this->orderByDependencies($targets);

        if ($mode === self::REPLACE) {
            // Children first, so the deletes hold even where the foreign keys
            // could not be switched off (a transaction is already open).
            foreach (array_reverse($ordered) as $table) {
                DB::table($table)->delete();
            }
        }

        $columns = [];
        $references = [];
        $nextIds = [];
        $idMaps = [];

        foreach ($ordered as $table) {
            $columns[$table] = $this->columns($table);
            $references[$table] = $sqlite ? $this->references($table) : [];
            $nextIds[$table] = (int) DB::table($table)->max('id');
        }

        foreach ($ordered as $table) {
            $dataset = $dump->datasets[$table];
            $rows = [];

            foreach ($dataset['rows'] as $values) {
                $row = [];

                foreach ($dataset['columns'] as $index => $column) {
                    if (in_array($column, $columns[$table], true)) {
                        $row[$column] = $values[$index] ?? null;
                    }
                }

                if ($row === []) {
                    continue;
                }

                if ($mode === self::APPEND) {
                    $row = $this->renumber($table, $row, $references[$table], $idMaps, $nextIds);
                }

                $rows[] = $row;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            $tables[] = ['table' => $table, 'rows' => count($rows), 'skipped' => false];
        }

        return $tables;
    }

    /**
     * Give one appended row a new id and move its foreign keys to the matching
     * parents' new ids.
     *
     * @param  array<string, string|null>  $row
     * @param  array<string, string>  $references
     * @param  array<string, array<string, int>>  $idMaps
     * @param  array<string, int>  $nextIds
     * @return array<string, string|null>
     */
    private function renumber(string $table, array $row, array $references, array &$idMaps, array &$nextIds): array
    {
        if (array_key_exists('id', $row)) {
            $previous = (string) $row['id'];

            $idMaps[$table][$previous] = ++$nextIds[$table];
            $row['id'] = (string) $idMaps[$table][$previous];
        }

        foreach ($references as $column => $parent) {
            if (! array_key_exists($column, $row) || $row[$column] === null) {
                continue;
            }

            $mapped = $idMaps[$parent][(string) $row[$column]] ?? null;

            if ($mapped !== null) {
                $row[$column] = (string) $mapped;
            }
        }

        return $row;
    }

    /**
     * @return array{0: list<array{table: string, rows: int, skipped: bool, note?: string}>, 1: int}
     */
    private function runStatements(SqlDump $dump, string $mode): array
    {
        $existing = $this->existingTables();
        $before = [];

        if ($mode === self::REPLACE) {
            foreach ($dump->insertTargets() as $table) {
                if (in_array($table, $existing, true)) {
                    DB::table($table)->delete();
                }
            }
        }

        foreach ($existing as $table) {
            $before[$table] = DB::table($table)->count();
        }

        $pdo = DB::connection()->getPdo();
        $statements = 0;
        $ledgerKept = false;

        foreach ($dump->statements as $statement) {
            if ($this->writesLedger($statement)) {
                $ledgerKept = true;

                continue;
            }

            $pdo->exec($statement);
            $statements++;
        }

        $tables = [];

        // A plain file may create the tables it inserts into, so what counts as
        // known is asked again once the statements have run.
        $existingAfter = $this->existingTables();

        foreach ($dump->tables() as $table) {
            $known = in_array($table, $existingAfter, true);

            $entry = [
                'table' => $table,
                'rows' => $known ? max(0, DB::table($table)->count() - ($before[$table] ?? 0)) : 0,
                'skipped' => ! $known,
            ];

            if ($ledgerKept && $table === self::LEDGER && $known) {
                $entry = [...$entry, 'note' => 'Migration history kept'];
            }

            $tables[] = $entry;
        }

        return [$tables, $statements];
    }

    /**
     * Whether a statement would rewrite this database's migration history,
     * which is the one table a restore from another server must leave alone.
     */
    private function writesLedger(string $statement): bool
    {
        return preg_match(
            '/^(?:INSERT\s+INTO|REPLACE\s+INTO|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?(?:\s+ONLY)?)\s+"?'.self::LEDGER.'"?\b/i',
            $statement,
        ) === 1;
    }

    /**
     * A copy of the database as it stands, so a replace is one `copy` away from
     * being undone. VACUUM INTO is used rather than a file copy because it takes
     * a consistent snapshot even while the database is in WAL mode.
     */
    private function backUp(): ?string
    {
        $path = (string) DB::connection()->getDatabaseName();

        if ($path === '' || $path === ':memory:' || ! is_file($path)) {
            return null;
        }

        $directory = storage_path('app/imports');
        File::ensureDirectoryExists($directory);

        $base = $directory.DIRECTORY_SEPARATOR.'database-'.now()->format('Y-m-d-His');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $target = $base.($attempt === 0 ? '' : '-'.$attempt).'.sqlite';

            if (file_exists($target)) {
                continue;
            }

            DB::statement("VACUUM INTO '".str_replace("'", "''", $target)."'");

            return $target;
        }

        throw new RuntimeException('Could not find a free name for the database backup.');
    }

    private function foreignKeysEnabled(): bool
    {
        $row = DB::selectOne('PRAGMA foreign_keys');

        return $row !== null && (bool) $row->foreign_keys;
    }

    /**
     * @return list<string>
     */
    private function existingTables(): array
    {
        return array_values(array_map(
            fn (mixed $table): string => (string) $table,
            Schema::getTableListing(schemaQualified: false),
        ));
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        return array_values(array_map(
            fn (mixed $column): string => (string) $column,
            Schema::getColumnListing($table),
        ));
    }

    /**
     * The columns of a table that point at another table, as column => parent.
     *
     * @return array<string, string>
     */
    private function references(string $table): array
    {
        $rows = DB::select('PRAGMA foreign_key_list("'.$this->quote($table).'")');
        $references = [];

        foreach ($rows as $row) {
            $references[(string) $row->from] = (string) $row->table;
        }

        return $references;
    }

    /**
     * Parents before children, so an appended row can be written after the row
     * it points at has a new id. A cycle falls back to the file's order.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function orderByDependencies(array $tables): array
    {
        $parents = [];

        foreach ($tables as $table) {
            $parents[$table] = array_values(array_intersect(array_values($this->references($table)), $tables));
        }

        $ordered = [];
        $pending = $tables;

        while ($pending !== []) {
            $placed = false;

            foreach ($pending as $index => $table) {
                if (array_diff($parents[$table], $ordered) === []) {
                    $ordered[] = $table;
                    unset($pending[$index]);
                    $placed = true;
                }
            }

            if (! $placed) {
                break;
            }
        }

        return array_merge($ordered, array_values($pending));
    }

    /**
     * @return list<string>
     */
    private function danglingReferences(): array
    {
        $rows = DB::select('PRAGMA foreign_key_check');
        $dangling = [];

        foreach (array_slice($rows, 0, 5) as $row) {
            $dangling[] = $row->table.' row '.$row->rowid.' has no '.$row->parent.' row';
        }

        return $dangling;
    }

    private function quote(string $identifier): string
    {
        return str_replace('"', '""', $identifier);
    }
}
