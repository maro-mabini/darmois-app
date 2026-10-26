<?php

use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordType;
use App\Models\RecordsOwner;
use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * The oldest year offered by the file date picker.
     */
    private const EARLIEST_YEAR = 1850;

    /**
     * How many years past the current one the picker reaches.
     */
    private const FUTURE_YEARS = 5;

    /**
     * The record being edited, or null when the form is creating one.
     */
    public ?int $editing = null;

    public string $owner_name = '';

    /**
     * The location the record is filed at, as the id of a managed location.
     * Optional: older records were filed before the list existed.
     */
    public string $location_id = '';

    /**
     * The DAR station that handles the record, as the id of a managed station.
     */
    public string $station_id = '';

    public string $owner_type_id = '';

    public string $record_type_id = '';

    /**
     * The record the file form is adding to, or null when it is closed.
     */
    public ?int $fileRecord = null;

    public string $file_name = '';

    public string $file_description = '';

    /**
     * The date the file was created, as Y-m-d, or an empty string for unset.
     */
    public string $date_of_file = '';

    /**
     * Free text matched against the owner name, owner type, record type name,
     * and the name or description of any file on the record.
     */
    #[Url(as: 'search', except: '')]
    public string $search = '';

    /**
     * The record type id to narrow the list to, or an empty string for all of them.
     *
     * Typed as a string so the "all types" blank round-trips through the URL.
     */
    #[Url(as: 'type', except: '')]
    public string $recordType = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRecordType(): void
    {
        $this->resetPage();
    }

    /**
     * Drop both filters and return to the first page.
     */
    public function clearFilters(): void
    {
        $this->reset('search', 'recordType');
    }

    /**
     * Reset the form so it is ready to create a record.
     */
    public function startCreate(): void
    {
        $this->editing = null;
        $this->owner_name = '';
        $this->location_id = '';
        $this->station_id = '';
        $this->owner_type_id = '';
        $this->record_type_id = '';
        $this->resetValidation();
    }

    /**
     * Load a record into the form.
     */
    public function startEdit(int $record): void
    {
        $model = RecordsOwner::query()->findOrFail($record);

        $this->editing = $model->id;
        $this->owner_name = $model->owner_name;
        $this->location_id = (string) $model->location_id;
        $this->station_id = (string) $model->station_id;
        $this->owner_type_id = (string) $model->owner_type_id;
        $this->record_type_id = (string) $model->record_type_id;
        $this->resetValidation();
    }

    /**
     * Create or update the record behind the form.
     *
     * Stays on the index rather than following the old create page's redirect:
     * the new row is at the top of the list, so there is nothing to navigate to.
     */
    public function save(): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $exception) {
            $this->dispatch('modal-open', 'record-form');

            throw $exception;
        }

        // A blank optional field is stored as null, not as an empty string, so
        // "has a location" stays a single question to ask of the column.
        $attributes = [
            'owner_name' => $validated['owner_name'],
            'location_id' => $this->location_id !== '' ? (int) $this->location_id : null,
            'station_id' => $this->station_id !== '' ? (int) $this->station_id : null,
            'owner_type_id' => $validated['owner_type_id'],
            'record_type_id' => $validated['record_type_id'],
        ];

        if ($this->editing === null) {
            RecordsOwner::create($attributes);

            $message = 'Record created.';
        } else {
            RecordsOwner::query()->findOrFail($this->editing)->update($attributes);

            $message = 'Record updated.';
        }

        $this->editing = null;
        $this->owner_name = '';
        $this->location_id = '';
        $this->station_id = '';
        $this->owner_type_id = '';
        $this->record_type_id = '';
        $this->resetValidation();
        // The list is newest first, so a new record belongs on the first page.
        $this->resetPage();

        $this->dispatch('modal-saved', 'record-form');
        $this->dispatch('toast', message: $message);
    }

    /**
     * Delete a record and, through the database cascade, its files.
     */
    public function delete(int $record): void
    {
        $model = RecordsOwner::query()->findOrFail($record);

        $model->delete();

        $this->dispatch('modal-saved', 'record-delete');
        $this->dispatch('toast', message: "Record “{$model->owner_name}” deleted.");
    }

    /**
     * Reset the file form so it is ready to add a file to the given record.
     *
     * The record is looked up rather than trusted so a stale row cannot open a
     * form that has nowhere to save to.
     */
    public function startCreateFile(int $record): void
    {
        $this->fileRecord = RecordsOwner::query()->findOrFail($record)->id;
        $this->file_name = '';
        $this->file_description = '';
        $this->date_of_file = '';
        $this->resetValidation();
    }

    /**
     * Attach the file described by the form to the record it was opened from.
     */
    public function saveFile(): void
    {
        if ($this->fileRecord === null) {
            return;
        }

        try {
            $validated = $this->validate($this->fileRules());
        } catch (ValidationException $exception) {
            $this->dispatch('modal-open', 'file-form');

            throw $exception;
        }

        // A blank optional field is stored as null, not as an empty string, so
        // "has a description" stays a single question to ask of the column.
        $dateOfFile = $validated['date_of_file'] ?? null;

        RecordsOwner::query()
            ->findOrFail($this->fileRecord)
            ->files()
            ->create([
                'file_name' => $validated['file_name'],
                'file_description' => $validated['file_description'] !== '' ? $validated['file_description'] : null,
                'date_of_file' => $dateOfFile !== '' ? $dateOfFile : null,
            ]);

        $this->fileRecord = null;
        $this->file_name = '';
        $this->file_description = '';
        $this->date_of_file = '';
        $this->resetValidation();

        $this->dispatch('modal-saved', 'file-form');
        $this->dispatch('toast', message: 'File added.');
    }

    /**
     * Whether anything is currently narrowing the list.
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->searchTerm() !== '' || $this->recordType !== '';
    }

    /**
     * The owner types offered by the form, keyed by their id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function ownerTypeOptions(): array
    {
        return OwnerType::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The record types offered by the form, keyed by their id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function recordTypeOptions(): array
    {
        return RecordType::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The locations offered by the form, keyed by their id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function locationOptions(): array
    {
        return Location::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The stations offered by the form, keyed by their id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function stationOptions(): array
    {
        return Station::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The twelve months, keyed by number so the value stays a plain integer.
     *
     * Names come from the intl-free month list rather than a formatted date so
     * they do not shift with the app's locale or the server's clock.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function monthOptions(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }

    /**
     * The oldest and newest year the file date picker will offer.
     *
     * The same bounds the validation rules enforce, so the picker can never
     * offer a year the server would then refuse.
     *
     * @return array{int, int}
     */
    #[Computed]
    public function fileYearRange(): array
    {
        return [self::EARLIEST_YEAR, (int) now()->year + self::FUTURE_YEARS];
    }

    /**
     * The owner of the record the file form is adding to, for the dialog's
     * description, or null while no record is chosen.
     */
    #[Computed]
    public function fileRecordName(): ?string
    {
        return $this->fileRecord === null
            ? null
            : RecordsOwner::query()->whereKey($this->fileRecord)->value('owner_name');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            // The blank option means "not set". Anything else has to name a
            // row that exists, so a stale id cannot be stored.
            'location_id' => $this->location_id === ''
                ? ['nullable']
                : ['integer', Rule::exists('locations', 'id')],
            'station_id' => $this->station_id === ''
                ? ['nullable']
                : ['integer', Rule::exists('stations', 'id')],
            'owner_type_id' => ['required', Rule::exists('owner_types', 'id')],
            'record_type_id' => ['required', Rule::exists('record_types', 'id')],
        ];
    }

    /**
     * The rules the file form is validated against.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function fileRules(): array
    {
        $current = (int) now()->year;

        return [
            'file_name' => ['required', 'string', 'max:255'],
            'file_description' => ['nullable', 'string', 'max:1000'],
            // One value, not a month and a year that could disagree: the picker
            // always hands over the whole date, so a partial one cannot arrive.
            'date_of_file' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.self::EARLIEST_YEAR.'-01-01',
                'before_or_equal:'.($current + self::FUTURE_YEARS).'-12-31',
            ],
        ];
    }

    /**
     * The records matching the current filters, newest first, each with its
     * file count and the files themselves for the expandable panel.
     *
     * @return LengthAwarePaginator<int, RecordsOwner>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return RecordsOwner::query()
            ->with([
                'recordType',
                'ownerType',
                'location',
                'station',
                'files' => fn (HasMany $query) => $query->latest(),
            ])
            ->withCount('files')
            ->when($this->searchTerm() !== '', function (Builder $query) {
                $term = '%'.$this->likePattern($this->searchTerm()).'%';

                $query->where(function (Builder $query) use ($term) {
                    $query->whereRaw("owner_name LIKE ? ESCAPE '\\'", [$term])
                        ->orWhereHas(
                            'ownerType',
                            fn (Builder $query) => $query->whereRaw("name LIKE ? ESCAPE '\\'", [$term]),
                        )
                        ->orWhereHas(
                            'recordType',
                            fn (Builder $query) => $query->whereRaw("name LIKE ? ESCAPE '\\'", [$term]),
                        )
                        // A record is a folder: finding it by something written
                        // on one of the papers inside is as useful as finding it
                        // by its own label.
                        ->orWhereHas('files', function (Builder $query) use ($term) {
                            $query->whereRaw("file_name LIKE ? ESCAPE '\\'", [$term])
                                ->orWhereRaw("file_description LIKE ? ESCAPE '\\'", [$term]);
                        });
                });
            })
            ->when(
                ($recordTypeId = $this->selectedRecordTypeId()) !== null,
                fn (Builder $query) => $query->where('record_type_id', $recordTypeId),
            )
            ->latest()
            ->paginate(15);
    }

    /**
     * The trimmed search term, or an empty string when the box holds only spaces.
     */
    private function searchTerm(): string
    {
        return trim($this->search);
    }

    /**
     * Escape LIKE wildcards so a literal % or _ is matched literally.
     *
     * The escape character is declared in the query rather than assumed: SQLite
     * treats a backslash as an ordinary character without an ESCAPE clause, so
     * whereLike() would leave whatever the user typed as a wildcard.
     */
    private function likePattern(string $term): string
    {
        return addcslashes($term, '\\%_');
    }

    /**
     * The requested record type id, or null when unfiltered or not a real id.
     */
    private function selectedRecordTypeId(): ?int
    {
        return ctype_digit($this->recordType) ? (int) $this->recordType : null;
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Records
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                @php($total = $this->records->total())
                {{ $total }} {{ Str::plural('record', $total) }} {{ $this->isFiltered ? 'match your filters.' : 'on file.' }}
            </p>
        </div>

        <div class="mt-4 flex gap-3 sm:mt-0">
            <a
                href="{{ route('admin.owner-types.index') }}"
                wire:navigate
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
            >
                Owner types
            </a>

            <a
                href="{{ route('admin.record-types.index') }}"
                wire:navigate
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
            >
                Record types
            </a>

            <button
                type="button"
                x-on:click="$wire.startCreate().then(() => $dispatch('modal-open', 'record-form'))"
                class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
            >
                New record
            </button>
        </div>
    </header>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative sm:w-80">
            <label for="record-search" class="sr-only">Search records</label>

            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-300">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </span>

            <input
                id="record-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search owner, type, record type or file"
                autocomplete="off"
                class="w-full rounded-lg border border-gray-300 bg-white py-2 pr-3 pl-9 text-sm text-gray-900 shadow-xs placeholder-gray-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-hidden [&::-webkit-search-cancel-button]:appearance-none dark:border-gray-700 dark:bg-white/5 dark:text-white dark:placeholder-gray-300"
            />
        </div>

        <div class="flex items-center gap-3">
            <label for="recordType" class="sr-only">Filter by record type</label>

            <div class="w-full sm:w-56">
                <x-form.combobox
                    name="recordType"
                    :options="$this->recordTypeOptions"
                    placeholder="All record types"
                    search-placeholder="Search record types…"
                    live
                />
            </div>

            @if ($this->isFiltered)
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="shrink-0 text-sm font-medium text-brand-600 hover:underline dark:text-brand-300"
                >
                    Clear filters
                </button>
            @endif
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950">
                <tr>
                    <th scope="col" class="w-10 py-3 pl-4 pr-1">
                        <span class="sr-only">Show files</span>
                    </th>
                    <th scope="col" class="px-3 py-3 text-left font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">Owner</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">Location</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">Station</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">Owner type</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">Record type</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">Files</th>
                    <th scope="col" class="px-3 py-3 text-left font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">Added</th>
                    <th scope="col" class="px-3 py-3 text-right font-medium whitespace-nowrap text-gray-500 dark:text-gray-300">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            @forelse ($this->records as $record)
                {{--
                    One <tbody> per record, so the row and the file panel below it
                    share an Alpine scope: clicking the row flips `open`, and the
                    panel is part of the same record rather than a sibling whose
                    state would have to be tracked by id on the component.
                --}}
                <tbody
                    wire:key="record-{{ $record->id }}"
                    x-data="{ open: false }"
                    class="divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <tr
                        x-on:click="if (! $event.target.closest('a, button, input, label')) open = ! open"
                        x-on:keydown.enter.prevent="open = ! open"
                        x-on:keydown.space.prevent="open = ! open"
                        tabindex="0"
                        :aria-expanded="open"
                        :aria-controls="'record-files-{{ $record->id }}'"
                        class="cursor-pointer select-none hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-600 dark:hover:bg-gray-950"
                    >
                        <td class="py-3 pl-4 pr-1 text-gray-400 dark:text-gray-300">
                            <span
                                class="inline-flex text-current transition-transform duration-150"
                                x-bind:class="{ 'rotate-90': open }"
                            >
                                <svg
                                    class="size-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                        </td>
                        <td class="px-3 py-3 font-medium whitespace-nowrap text-gray-900 dark:text-white">
                            <a
                                href="{{ route('admin.records.show', $record) }}"
                                wire:navigate
                                class="hover:text-brand-600 hover:underline dark:hover:text-brand-300"
                            >
                                {{ $record->owner_name }}
                            </a>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            {{ $record->location?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            {{ $record->station?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                {{ $record->ownerType->name }}
                            </span>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                {{ $record->recordType->name }}
                            </span>
                        </td>
                        <td class="px-3 py-3 tabular-nums text-gray-600 dark:text-gray-300">
                            {{ $record->files_count }}
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            {{ $record->created_at->toFormattedDateString() }}
                        </td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">
                            <a
                                href="{{ route('admin.records.show', $record) }}"
                                wire:navigate
                                class="font-medium text-gray-600 hover:text-gray-900 hover:underline dark:text-gray-300 dark:hover:text-white"
                            >
                                View
                            </a>

                            <button
                                type="button"
                                x-on:click="$wire.startEdit({{ $record->id }}).then(() => $dispatch('modal-open', 'record-form'))"
                                class="ml-3 font-medium text-brand-600 hover:underline dark:text-brand-300"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                data-label="{{ $record->owner_name }}"
                                x-on:click="$dispatch('modal-open', { id: 'record-delete', payload: { id: {{ $record->id }}, label: $el.dataset.label } })"
                                class="ml-3 font-medium text-red-600 hover:underline dark:text-red-400"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>

                    <tr
                        id="record-files-{{ $record->id }}"
                        x-show="open"
                        x-cloak
                        class="bg-gray-50/70 dark:bg-gray-950/50"
                    >
                        <td colspan="9" class="px-4 py-4">
                            <div class="flex items-center justify-between gap-4">
                                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                    Files on this record

                                    <span class="inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium tabular-nums text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $record->files_count }}
                                    </span>
                                </h2>

                                <button
                                    type="button"
                                    x-on:click="$wire.startCreateFile({{ $record->id }}).then(() => $dispatch('modal-open', 'file-form'))"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-brand-500"
                                >
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>

                                    Add file
                                </button>
                            </div>

                            @if ($record->files->isEmpty())
                                <p class="mt-3 text-sm text-gray-500 dark:text-gray-300">
                                    No files on this record yet.
                                </p>
                            @else
                                <ul role="list" class="mt-3 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($record->files as $file)
                                        <li wire:key="record-{{ $record->id }}-file-{{ $file->id }}" class="py-2">
                                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                                <span class="font-medium text-gray-900 dark:text-white">
                                                    {{ $file->file_name }}
                                                </span>

                                                <span class="text-xs whitespace-nowrap tabular-nums text-gray-500 dark:text-gray-300">
                                                    {{ $file->date_of_file?->toFormattedDateString() ?? 'No date' }}
                                                </span>
                                            </div>

                                            @if ($file->file_description)
                                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                                                    {{ $file->file_description }}
                                                </p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-gray-500 dark:text-gray-300">
                            @if ($this->isFiltered)
                                No records match your search.
                                <button
                                    type="button"
                                    wire:click="clearFilters"
                                    class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                                >
                                    Clear filters
                                </button>
                            @else
                                No records yet.
                            @endif
                        </td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>

    @if ($this->records->hasPages())
        <div class="mt-6">
            {{ $this->records->links() }}
        </div>
    @endif

    <x-modal
        id="record-form"
        :title="$this->editing === null ? 'New record' : 'Edit record'"
        description="A record groups the files that belong to one owner."
    >
        <form id="record-form-element" wire:submit="save" class="space-y-5">
            <x-form.field label="Owner name" name="owner_name" required>
                <x-form.input name="owner_name" autocomplete="off" placeholder="e.g. Marie Curie" />
            </x-form.field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field label="Location" name="location_id">
                    <x-form.combobox
                        name="location_id"
                        :options="$this->locationOptions"
                        placeholder="No location"
                        search-placeholder="Search locations…"
                    />
                </x-form.field>

                <x-form.field label="Station" name="station_id">
                    <x-form.combobox
                        name="station_id"
                        :options="$this->stationOptions"
                        placeholder="No station"
                        search-placeholder="Search stations…"
                    />
                </x-form.field>
            </div>

            <x-form.field label="Owner type" name="owner_type_id" required>
                <x-form.combobox
                    name="owner_type_id"
                    :options="$this->ownerTypeOptions"
                    placeholder="Select an owner type"
                    search-placeholder="Search owner types…"
                />
            </x-form.field>

            <x-form.field label="Record type" name="record_type_id" required>
                <x-form.combobox
                    name="record_type_id"
                    :options="$this->recordTypeOptions"
                    placeholder="Select a record type"
                    search-placeholder="Search record types…"
                />
            </x-form.field>
        </form>

        <x-slot:footer>
            <button
                type="button"
                x-on:click="$refs.dialog.close()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
            >
                Cancel
            </button>

            <button
                type="submit"
                form="record-form-element"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
            >
                {{ $this->editing === null ? 'Create record' : 'Save changes' }}
            </button>
        </x-slot:footer>
    </x-modal>

    <x-modal
        id="file-form"
        title="Add file"
        :description="$this->fileRecordName ? 'Attach a file to '.$this->fileRecordName.'.' : null"
    >
        <form id="file-form-element" wire:submit="saveFile" class="space-y-5">
            <x-form.field label="File name" name="file_name" required>
                <x-form.input name="file_name" autocomplete="off" />
            </x-form.field>

            <x-form.field label="Description" name="file_description">
                <x-form.textarea name="file_description" rows="3" />
            </x-form.field>

            <x-form.date-picker
                name="date_of_file"
                label="Date of file"
                :months="$this->monthOptions"
                :min-year="$this->fileYearRange[0]"
                :max-year="$this->fileYearRange[1]"
            />
        </form>

        <x-slot:footer>
            <button
                type="button"
                x-on:click="$refs.dialog.close()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
            >
                Cancel
            </button>

            <button
                type="submit"
                form="file-form-element"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
            >
                Add file
            </button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal
        id="record-delete"
        title="Delete record?"
        message="&#8212; this deletes it and all of its files."
        action="delete"
        confirm-label="Delete record"
    />
</div>
