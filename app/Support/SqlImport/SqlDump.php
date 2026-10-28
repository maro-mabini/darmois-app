<?php

namespace App\Support\SqlImport;

/**
 * A .sql file read into the shape the importer needs.
 *
 * A pg_dump is recognised by its `COPY ... FROM stdin` blocks: those rows are
 * lifted out into plain arrays so they can be put into the app's own schema
 * instead of replaying PostgreSQL's DDL (which SQLite could not run). Anything
 * else is kept as a list of statements and executed as written.
 */
final class SqlDump
{
    public const POSTGRES = 'postgres';

    public const PLAIN = 'plain';

    /**
     * @param  array<string, array{columns: list<string>, rows: list<list<string|null>>}>  $datasets
     * @param  list<string>  $statements
     */
    private function __construct(
        public readonly string $dialect,
        public readonly array $datasets,
        public readonly array $statements,
    ) {}

    public static function fromPath(string $path): self
    {
        $sql = (string) file_get_contents($path);

        if (! self::isPostgresDump($sql)) {
            return new self(self::PLAIN, [], self::splitStatements($sql));
        }

        [$datasets, $rest] = self::copyBlocks($sql);

        return new self(self::POSTGRES, $datasets, self::postgresStatements($rest));
    }

    /**
     * Every table the file mentions, whether it carries rows or only DDL.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        if ($this->datasets !== []) {
            return array_keys($this->datasets);
        }

        return self::tablesInStatements($this->statements, '/^INSERT\s+INTO|^REPLACE\s+INTO|^CREATE\s+TABLE|^DROP\s+TABLE|^UPDATE|^DELETE\s+FROM/i');
    }

    /**
     * The tables a plain SQL file writes rows into. Replace mode empties these
     * first; DDL is left to the file itself, which usually drops and recreates.
     *
     * @return list<string>
     */
    public function insertTargets(): array
    {
        return array_values(array_filter(
            self::tablesInStatements($this->statements, '/^INSERT\s+INTO|^REPLACE\s+INTO/i'),
            fn (string $table): bool => $table !== DatabaseImporter::LEDGER,
        ));
    }

    public function rowCount(): int
    {
        return array_sum(array_map(
            fn (array $dataset): int => count($dataset['rows']),
            $this->datasets,
        ));
    }

    private static function isPostgresDump(string $sql): bool
    {
        return preg_match('/^COPY\s+\S+\s+\(.*\)\s+FROM stdin;$/m', $sql) === 1
            || str_contains($sql, 'pg_dump')
            || preg_match('/^SET statement_timeout/m', $sql) === 1;
    }

    /**
     * Pull every COPY block out of the file, leaving the rest for the
     * statement pass.
     *
     * @return array{0: array<string, array{columns: list<string>, rows: list<list<string|null>>}>, 1: string}
     */
    private static function copyBlocks(string $sql): array
    {
        $datasets = [];
        $rest = [];
        $current = null;

        foreach (preg_split('/\r\n|\r|\n/', $sql) ?: [] as $line) {
            if (preg_match('/^COPY\s+(?:"?public"?\.)?"?(\w+)"?\s*\(([^)]*)\)\s+FROM stdin;$/i', $line, $matches) === 1) {
                $current = [
                    'table' => $matches[1],
                    'columns' => array_map(
                        fn (string $column): string => trim(trim($column), '"'),
                        explode(',', $matches[2]),
                    ),
                    'rows' => [],
                ];

                continue;
            }

            if ($current === null) {
                $rest[] = $line;

                continue;
            }

            if ($line === '\\.') {
                $datasets[$current['table']] = [
                    'columns' => $current['columns'],
                    'rows' => $current['rows'],
                ];
                $current = null;

                continue;
            }

            $current['rows'][] = array_map(self::decodeCopyField(...), explode("\t", $line));
        }

        return [$datasets, implode("\n", $rest)];
    }

    /**
     * Decode one field of PostgreSQL's COPY text format.
     */
    private static function decodeCopyField(string $raw): ?string
    {
        if ($raw === '\N') {
            return null;
        }

        $decoded = '';
        $length = strlen($raw);

        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];

            if ($char !== '\\' || $i === $length - 1) {
                $decoded .= $char;

                continue;
            }

            $decoded .= match ($raw[++$i]) {
                'b' => "\x08",
                'f' => "\x0C",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'v' => "\x0B",
                default => $raw[$i],
            };
        }

        return $decoded;
    }

    /**
     * What is left of a pg_dump once the rows are out: psql meta commands and
     * the DDL go, and the data statements lose their `public.` qualifier so
     * they land on the app's own tables.
     *
     * @return list<string>
     */
    private static function postgresStatements(string $sql): array
    {
        $sql = (string) preg_replace('/^\\\\.*$/m', '', $sql);

        $statements = [];

        foreach (self::splitStatements($sql) as $statement) {
            if (preg_match('/^(INSERT\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE|REPLACE\s+INTO)\b/i', $statement) !== 1) {
                continue;
            }

            $statements[] = (string) preg_replace(
                '/^((?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?(?:\s+ONLY)?|REPLACE\s+INTO)\s+)("?public"?\.)/i',
                '$1',
                $statement,
                1,
            );
        }

        return $statements;
    }

    /**
     * Split a script on the semicolons that actually end a statement, leaving
     * quotes, comments and doubled escape quotes alone.
     *
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $current .= $char;

                if ($char === $quote) {
                    if ($next === $quote) {
                        $current .= $next;
                        $i++;

                        continue;
                    }

                    $quote = null;
                } elseif ($char === '\\' && $quote !== '"' && $next !== '') {
                    $current .= $next;
                    $i++;
                }

                continue;
            }

            if ($char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                $current .= ' ';

                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                $current .= ' ';

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;

                continue;
            }

            if ($char === ';') {
                $statement = trim($current);

                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $current = '';

                continue;
            }

            $current .= $char;
        }

        $statement = trim($current);

        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * @param  list<string>  $statements
     * @return list<string>
     */
    private static function tablesInStatements(array $statements, string $pattern): array
    {
        $tables = [];

        foreach ($statements as $statement) {
            if (preg_match($pattern, $statement) !== 1) {
                continue;
            }

            preg_match_all(
                '/\b(?:INSERT\s+INTO|REPLACE\s+INTO|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|DROP\s+TABLE(?:\s+IF\s+EXISTS)?|UPDATE|DELETE\s+FROM)\s+"?(?:public"?\.)?"?(\w+)"?/i',
                $statement,
                $matches,
            );

            foreach ($matches[1] as $table) {
                if (! str_starts_with($table, 'sqlite_')) {
                    $tables[$table] = true;
                }
            }
        }

        return array_keys($tables);
    }
}
