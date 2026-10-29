<?php

use App\Support\SqlImport\DatabaseImporter;
use App\Support\SqlImport\SqlDump;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    /**
     * The .sql file chosen in the form, before it is imported.
     */
    public mixed $file = null;

    /**
     * What importing does to the rows already here.
     */
    public string $mode = DatabaseImporter::APPEND;

    /**
     * What the chosen file holds, read on upload so the page can say so before
     * anything is written.
     *
     * @var array{dialect: string, tables: list<string>, rows: int|null, size: int}|null
     */
    public ?array $preview = null;

    /**
     * What the last import did.
     *
     * @var array{
     *     dialect: string,
     *     mode: string,
     *     backup: string|null,
     *     tables: list<array{table: string, rows: int, skipped: bool, note?: string}>,
     *     statements: int,
     *     dangling: list<string>
     * }|null
     */
    public ?array $report = null;

    /**
     * Executing uploaded SQL is a development tool: there is no login in front of
     * it, so a deployed server must not offer it at all. This runs on every
     * request, not just the first one, so a crafted component update is refused
     * too.
     */
    public function boot(): void
    {
        abort_if(app()->isProduction(), 404);
    }

    /**
     * Read the headers of the file as soon as it arrives, so the form can show
     * the dialect and the tables before the user commits to importing.
     */
    public function updatedFile(): void
    {
        $this->reset('report', 'preview');
        $this->validateFile();

        $dump = SqlDump::fromPath($this->uploadedFile()->getRealPath());

        $this->preview = [
            'dialect' => $dump->dialect,
            'tables' => $dump->tables(),
            'rows' => $dump->dialect === SqlDump::POSTGRES ? $dump->rowCount() : null,
            'size' => $this->uploadedFile()->getSize(),
        ];
    }

    /**
     * Load the file into the database.
     */
    public function import(DatabaseImporter $importer): void
    {
        $this->reset('report');
        $this->validateFile();

        try {
            // A dump carries data, not this app's schema: make sure the tables
            // it is about to fill exist before the rows land.
            Artisan::call('migrate', ['--force' => true]);

            $this->report = $importer->import(
                SqlDump::fromPath($this->uploadedFile()->getRealPath()),
                $this->mode,
            );
        } catch (Throwable $exception) {
            $this->addError('import', $exception->getMessage());

            return;
        }

        $rows = array_sum(array_column($this->report['tables'], 'rows'));
        $statements = $this->report['statements'];

        $this->dispatch('toast', message: match (true) {
            $rows === 1 => '1 row imported.',
            $rows > 1 => number_format($rows).' rows imported.',
            $statements === 1 => '1 statement run.',
            $statements > 1 => number_format($statements).' statements run.',
            default => 'Nothing in that file was imported.',
        });
    }

    private function validateFile(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:20480', 'extensions:sql,txt'],
            'mode' => ['required', 'in:append,replace'],
        ]);
    }

    private function uploadedFile(): UploadedFile
    {
        if (! $this->file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'Choose a .sql file first.']);
        }

        return $this->file;
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <a
        href="{{ route('admin.settings.index') }}"
        wire:navigate
        class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:text-brand-300"
    >
        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Back to Settings
    </a>

    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
            Import
        </h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
            Load a .sql file into this database. A pg_dump from the old PostgreSQL server is translated from
            psql's dialect into the tables this SQLite database already has; plain SQL is run as written.
        </p>
    </header>

    @if ($report !== null)
        <x-panel
            class="mt-8"
            title="Import finished"
            :description="$report['dialect'] === 'postgres'
                ? 'PostgreSQL dump, loaded from its COPY blocks into the tables this app already has.'
                : 'SQL statements run in the order the file wrote them.'"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Mode</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $report['mode'] === 'replace' ? 'Replaced' : 'Appended' }}
                    </p>
                </div>

                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                    @if ($report['dialect'] === 'postgres')
                        <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Tables loaded</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ collect($report['tables'])->where('skipped', false)->count() }}
                        </p>
                    @else
                        <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Statements run</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ number_format($report['statements']) }}
                        </p>
                    @endif
                </div>

                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Rows written</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ number_format(array_sum(array_column($report['tables'], 'rows'))) }}
                    </p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                            <th class="px-3 py-2">Table</th>
                            <th class="px-3 py-2">Rows</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($report['tables'] as $entry)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-gray-900 dark:text-white">
                                    {{ $entry['table'] }}
                                </td>
                                <td class="px-3 py-2 tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ $entry['skipped'] ? '—' : number_format($entry['rows']) }}
                                </td>
                                <td class="px-3 py-2">
                                    @if ($entry['skipped'])
                                        <span class="inline-flex rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                            {{ $entry['note'] ?? 'Not in this schema' }}
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                            {{ $report['dialect'] === 'postgres' ? 'Loaded' : 'Touched' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($report['backup'] !== null)
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    The database from before this import was saved to
                    <code class="font-mono text-gray-700 dark:text-gray-200">{{ str_replace(base_path().DIRECTORY_SEPARATOR, '', $report['backup']) }}</code>.
                </p>
            @endif

            @if ($report['dangling'] !== [])
                <div class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    <p class="font-medium">Some rows point at parents that are not in the database:</p>
                    <ul class="mt-1 list-disc pl-5 text-xs">
                        @foreach ($report['dangling'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-panel>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <x-panel title="SQL file" description="One .sql file, up to 20 MB." class="lg:col-span-2">
            <label
                for="file"
                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 px-6 py-10 text-center transition hover:border-brand-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-brand-400 dark:hover:bg-white/5"
            >
                <svg class="size-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 7.5 7.5 12M12 7.5V21" />
                </svg>

                <span class="mt-3 text-sm font-medium text-gray-900 dark:text-white">
                    {{ $file === null ? 'Choose a .sql file' : 'Choose another file' }}
                </span>
                <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    PostgreSQL dump or plain SQL
                </span>

                <input id="file" name="file" type="file" wire:model="file" accept=".sql,.txt" class="sr-only" />
            </label>

            <p wire:loading wire:target="file" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Reading the file&hellip;
            </p>

            @error('file')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if ($file !== null)
                <div class="mt-4 rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                        {{ $file->getClientOriginalName() }}
                    </p>

                    @if ($preview !== null)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $preview['dialect'] === 'postgres' ? 'PostgreSQL dump' : 'Plain SQL' }}
                            &middot; {{ count($preview['tables']) }}
                            {{ count($preview['tables']) === 1 ? 'table' : 'tables' }}
                            @if ($preview['rows'] !== null)
                                &middot; {{ number_format($preview['rows']) }} rows
                            @endif
                            &middot; {{ number_format($preview['size'] / 1024, 1) }} KB
                        </p>

                        @if ($preview['tables'] !== [])
                            <p class="mt-2 font-mono text-xs break-all text-gray-500 dark:text-gray-400">
                                {{ implode(', ', $preview['tables']) }}
                            </p>
                        @endif
                    @endif
                </div>
            @endif

            <fieldset class="mt-6">
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    How to load it
                </legend>

                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <label
                        @class([
                            'flex cursor-pointer gap-3 rounded-xl border p-4 transition',
                            'border-brand-600 bg-brand-50/60 ring-1 ring-brand-600 dark:border-brand-400 dark:bg-brand-500/10' => $mode === 'append',
                            'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600' => $mode !== 'append',
                        ])
                    >
                        <input type="radio" wire:model.live="mode" value="append" class="mt-0.5 size-4 accent-brand-600" />

                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">Append</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                Keeps every row already here and adds the file's rows after them with new
                                ids, so nothing collides.
                            </span>
                        </span>
                    </label>

                    <label
                        @class([
                            'flex cursor-pointer gap-3 rounded-xl border p-4 transition',
                            'border-red-600 bg-red-50/60 ring-1 ring-red-600 dark:border-red-400 dark:bg-red-500/10' => $mode === 'replace',
                            'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600' => $mode !== 'replace',
                        ])
                    >
                        <input type="radio" wire:model.live="mode" value="replace" class="mt-0.5 size-4 accent-red-600" />

                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-red-700 dark:text-red-400">Replace</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                Empties every table the file covers, then loads it. A copy of the database is
                                saved first.
                            </span>
                        </span>
                    </label>
                </div>
            </fieldset>

            @error('import')
                <div class="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
                    {{ $message }}
                </div>
            @enderror

            <div class="mt-6 flex items-center gap-3">
                @if ($mode === 'replace')
                    <button
                        type="button"
                        x-on:click="$dispatch('modal-open', 'import-replace')"
                        @disabled($file === null)
                        class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Replace and import
                    </button>
                @else
                    <button
                        type="button"
                        wire:click="import"
                        wire:loading.attr="disabled"
                        wire:target="import"
                        @disabled($file === null)
                        class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="import">Import</span>
                        <span wire:loading wire:target="import">Importing&hellip;</span>
                    </button>
                @endif

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Nothing is written until you press the button.
                </p>
            </div>
        </x-panel>

        <x-panel title="What it does" description="The rules this page follows.">
            <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <li class="flex gap-2">
                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-600"></span>
                    <span>
                        A <span class="font-medium text-gray-900 dark:text-white">PostgreSQL dump</span> is read
                        from its COPY blocks. Rows go into the tables this app already has; tables it does not
                        have (<span class="font-mono text-xs">sessions</span>,
                        <span class="font-mono text-xs">jobs</span>) are skipped and listed.
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-600"></span>
                    <span>
                        <span class="font-medium text-gray-900 dark:text-white">PostgreSQL differences are
                        translated:</span>
                        <span class="font-mono text-xs">public.</span> prefixes and the
                        <span class="font-mono text-xs">SET</span> / <span class="font-mono text-xs">COPY</span> /
                        <span class="font-mono text-xs">ALTER … OWNER</span> commands SQLite cannot run are
                        dropped, and NULLs written as <span class="font-mono text-xs">\N</span> are read back.
                        Ids the file carries are kept, so sequences stay ahead of them and no later insert
                        collides.
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                    <span>
                        The <span class="font-medium text-gray-900 dark:text-white">migration history</span> in
                        the file belongs to the other server, so it is reported but never loaded over this
                        database's own — otherwise the next migrate would try to rebuild tables that exist.
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-600"></span>
                    <span>
                        <span class="font-medium text-gray-900 dark:text-white">Plain SQL</span> is split into
                        statements and run as written.
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-600"></span>
                    <span>
                        The app's migrations run first, so a dump can seed a database that has no tables yet.
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-600"></span>
                    <span>
                        Foreign keys are checked afterwards; anything left pointing at a missing parent is
                        reported on the result.
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                    <span>
                        The page only exists outside production. A deployed server answers 404.
                    </span>
                </li>
            </ul>
        </x-panel>
    </div>

    <x-modal
        id="import-replace"
        title="Replace the database?"
        description="Every table the file covers is emptied before it loads."
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">
            The copy saved beforehand lands in
            <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">
                storage/app/imports
            </code>,
            so this can be undone by hand.
        </p>

        <x-slot:footer>
            <button
                type="button"
                x-on:click="$refs.dialog.close()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
            >
                Cancel
            </button>

            <button
                type="button"
                wire:loading.attr="disabled"
                wire:target="import"
                x-on:click="$wire.import().then(() => $refs.dialog.close())"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Replace and import
            </button>
        </x-slot:footer>
    </x-modal>
</div>
