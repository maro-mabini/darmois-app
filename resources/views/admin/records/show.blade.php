<?php

use App\Models\File;
use App\Models\OwnerType;
use App\Models\RecordType;
use App\Models\RecordsOwner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * The oldest year offered by the date picker.
     */
    private const EARLIEST_YEAR = 1850;

    /**
     * How many years past the current one the picker reaches.
     */
    private const FUTURE_YEARS = 5;

    public RecordsOwner $record;

    /**
     * Whether the record form is open for editing this record.
     */
    public bool $editingRecord = false;

    public string $owner_name = '';

    public string $owner_type_id = '';

    public string $record_type_id = '';

    /**
     * The file being edited, or null when the file form is creating one.
     */
    public ?int $editingFile = null;

    public string $file_name = '';

    public string $file_description = '';

    /**
     * The month the file was created in, 1-12, or an empty string for unset.
     */
    public string $date_of_file_month = '';

    /**
     * The year the file was created in, or an empty string for unset.
     */
    public string $date_of_file_year = '';

    /**
     * The date the file being edited already had, kept so re-saving an untouched
     * month does not quietly move the file to the first of the month.
     */
    public string $date_of_file_original = '';

    /**
     * Load the record.
     */
    public function mount(RecordsOwner $record): void
    {
        $this->record = $record;
    }

    /**
     * The record's files, newest first.
     *
     * @return Collection<int, File>
     */
    #[Computed]
    public function files()
    {
        return $this->record->files()->latest()->get();
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
     * The range is deliberately wide at the bottom: these are archival records
     * and the oldest file in the sample data is from the 1890s, so a range
     * anchored on the current year would make historic files unreachable. The
     * top reaches past the current year to allow a date that is known but not
     * yet reached.
     *
     * These are the same bounds the validation rules enforce, so the picker can
     * never offer a year the server would then refuse.
     *
     * @return array{int, int}
     */
    #[Computed]
    public function fileYearRange(): array
    {
        return [self::EARLIEST_YEAR, (int) now()->year + self::FUTURE_YEARS];
    }

    /**
     * Load the record into the form.
     */
    public function startEditRecord(): void
    {
        $this->owner_name = $this->record->owner_name;
        $this->owner_type_id = (string) $this->record->owner_type_id;
        $this->record_type_id = (string) $this->record->record_type_id;
        $this->editingRecord = true;
        $this->resetValidation();
    }

    /**
     * Persist the changes to the record.
     */
    public function saveRecord(): void
    {
        try {
            $validated = $this->validate([
                'owner_name' => ['required', 'string', 'max:255'],
                'owner_type_id' => ['required', Rule::exists('owner_types', 'id')],
                'record_type_id' => ['required', Rule::exists('record_types', 'id')],
            ]);
        } catch (ValidationException $exception) {
            $this->dispatch('modal-open', 'record-form');

            throw $exception;
        }

        $this->record->update($validated);

        $this->editingRecord = false;
        $this->resetValidation();

        $this->dispatch('modal-saved', 'record-form');
        $this->dispatch('toast', message: 'Record updated.');
    }

    /**
     * Reset the file form so it is ready to add one.
     */
    public function startCreateFile(): void
    {
        $this->editingFile = null;
        $this->file_name = '';
        $this->file_description = '';
        $this->date_of_file_month = '';
        $this->date_of_file_year = '';
        $this->date_of_file_original = '';
        $this->resetValidation();
    }

    /**
     * Load a file into the form.
     *
     * Scoped through the record so that a file can never be reached through a
     * record it does not belong to.
     */
    public function startEditFile(int $file): void
    {
        $model = $this->record->files()->findOrFail($file);

        $this->editingFile = $model->id;
        $this->file_name = $model->file_name;
        $this->file_description = (string) $model->file_description;
        // format('n') and 'Y' rather than a split, so no timezone or padding
        // assumption creeps in. The original is kept so an unchanged month
        // survives the round trip intact.
        $this->date_of_file_month = $model->date_of_file?->format('n') ?? '';
        $this->date_of_file_year = $model->date_of_file?->format('Y') ?? '';
        $this->date_of_file_original = $model->date_of_file?->format('Y-m-d') ?? '';
        $this->resetValidation();
    }

    /**
     * Create or update the file behind the form.
     */
    public function saveFile(): void
    {
        try {
            $validated = $this->validate($this->fileRules());
        } catch (ValidationException $exception) {
            $this->dispatch('modal-open', 'file-form');

            throw $exception;
        }

        // A blank optional field is stored as null, not as an empty string, so
        // that "has a description" stays a single question to ask of the column.
        $attributes = [
            'file_name' => $validated['file_name'],
            'file_description' => $validated['file_description'] !== '' ? $validated['file_description'] : null,
            'date_of_file' => $this->composeFileDate(),
        ];

        $editing = $this->editingFile !== null;

        if ($editing) {
            $this->record->files()->findOrFail($this->editingFile)->update($attributes);
        } else {
            $this->record->files()->create($attributes);
        }

        $this->editingFile = null;
        $this->file_name = '';
        $this->file_description = '';
        $this->date_of_file_month = '';
        $this->date_of_file_year = '';
        $this->date_of_file_original = '';
        $this->resetValidation();

        $this->dispatch('modal-saved', 'file-form');
        $this->dispatch('toast', message: $editing ? 'File updated.' : 'File added.');
    }

    /**
     * Join the two dropdowns into a date, or null when neither is set.
     *
     * The dropdowns only carry a month and a year, so the day is implied. Where
     * the file already had a date in that same month, the original day is kept
     * rather than replaced with 01: editing a file's name should not silently
     * move it to a different day of the month.
     */
    private function composeFileDate(): ?string
    {
        if ($this->date_of_file_month === '' || $this->date_of_file_year === '') {
            return null;
        }

        $month = sprintf('%02d', (int) $this->date_of_file_month);
        $year = (int) $this->date_of_file_year;

        // Only carry the original day over when it belongs to the chosen month.
        if ($this->date_of_file_original !== '') {
            $original = Carbon::parse($this->date_of_file_original);

            if ($original->format('Y') === (string) $year && $original->format('n') === (string) (int) $this->date_of_file_month) {
                return $original->format('Y-m-d');
            }
        }

        return sprintf('%04d-%02d-01', $year, (int) $this->date_of_file_month);
    }

    /**
     * Delete one of the record's files.
     */
    public function deleteFile(int $file): void
    {
        $model = $this->record->files()->findOrFail($file);

        $model->delete();

        $this->dispatch('modal-saved', 'file-delete');
        $this->dispatch('toast', message: "File “{$model->file_name}” deleted.");
    }

    /**
     * Delete the record and, through the database cascade, its files.
     *
     * The id is only accepted to match the record this page is already scoped
     * to: the confirm dialog passes the row it was opened from, and anything
     * else is ignored rather than trusted.
     */
    public function deleteRecord(int $record): void
    {
        if ($record !== $this->record->id) {
            return;
        }

        $name = $this->record->owner_name;

        $this->record->delete();

        // Dispatched before the redirect on purpose. Both are effects on the same
        // response, and the toast is what survives the navigation: it is written
        // to sessionStorage as it is handled, and the index page's layout reads
        // it back on init. Reversing these two lines would navigate first and
        // discard the notification before it was ever shown.
        $this->dispatch('toast', message: "Record “{$name}” deleted.");

        $this->redirect(route('admin.records.index'), navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function fileRules(): array
    {
        $current = (int) now()->year;

        return [
            'file_name' => ['required', 'string', 'max:255'],
            'file_description' => ['nullable', 'string', 'max:1000'],
            // Each half is optional, but neither may be filled in alone: a month
            // with no year is not a date, and a bare year is not one either.
            'date_of_file_month' => ['nullable', 'integer', 'between:1,12', 'required_with:date_of_file_year'],
            'date_of_file_year' => [
                'nullable',
                'integer',
                "min:".self::EARLIEST_YEAR,
                'max:'.($current + self::FUTURE_YEARS),
                'required_with:date_of_file_month',
            ],
        ];
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <nav class="text-sm">
        <a
            href="{{ route('admin.records.index') }}"
            wire:navigate
            class="font-medium text-indigo-600 hover:underline dark:text-indigo-300"
        >
            &larr; All records
        </a>
    </nav>

    <header class="mt-4 sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                {{ $this->record->owner_name }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                {{ $this->record->ownerType->name }} &middot;
                {{ $this->record->recordType->name }} &middot;
                added {{ $this->record->created_at->toFormattedDateString() }}
            </p>
        </div>

        <div class="mt-4 flex gap-3 sm:mt-0">
            <button
                type="button"
                x-on:click="$wire.startEditRecord().then(() => $dispatch('modal-open', 'record-form'))"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
            >
                Edit record
            </button>

            <button
                type="button"
                data-label="{{ $this->record->owner_name }}"
                x-on:click="$dispatch('modal-open', { id: 'record-delete', payload: { id: {{ $this->record->id }}, label: $el.dataset.label } })"
                class="inline-flex items-center rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 shadow-xs hover:bg-red-50 dark:border-red-500/40 dark:bg-white/5 dark:text-red-300 dark:hover:bg-red-500/10"
            >
                Delete record
            </button>

            <button
                type="button"
                x-on:click="$wire.startCreateFile().then(() => $dispatch('modal-open', 'file-form'))"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500"
            >
                Add file
            </button>
        </div>
    </header>

    <div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">File</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Description</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Date of file</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-300">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($this->files as $file)
                    <tr wire:key="file-{{ $file->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-950">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {{ $file->file_name }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                            {{ $file->file_description ?: '—' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            {{ $file->date_of_file?->format('F Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button
                                type="button"
                                x-on:click="$wire.startEditFile({{ $file->id }}).then(() => $dispatch('modal-open', 'file-form'))"
                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-300"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                data-label="{{ $file->file_name }}"
                                x-on:click="$dispatch('modal-open', { id: 'file-delete', payload: { id: {{ $file->id }}, label: $el.dataset.label } })"
                                class="ml-3 font-medium text-red-600 hover:underline dark:text-red-400"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-16 text-center text-gray-500 dark:text-gray-300">
                            No files on this record yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal
        id="record-form"
        title="Edit record"
        description="A record groups the files that belong to one owner."
    >
        <form id="record-form-element" wire:submit="saveRecord" class="space-y-5">
            <x-form.field label="Owner name" name="owner_name" required>
                <x-form.input name="owner_name" autocomplete="off" />
            </x-form.field>

            <x-form.field label="Owner type" name="owner_type_id" required>
                <x-form.select
                    name="owner_type_id"
                    :options="$this->ownerTypeOptions"
                    placeholder="Select an owner type"
                />
            </x-form.field>

            <x-form.field label="Record type" name="record_type_id" required>
                <x-form.select
                    name="record_type_id"
                    :options="$this->recordTypeOptions"
                    placeholder="Select a record type"
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
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500"
            >
                Save changes
            </button>
        </x-slot:footer>
    </x-modal>

    <x-modal
        id="file-form"
        :title="$this->editingFile === null ? 'Add file' : 'Edit file'"
        :description="'Attach a file to '.$this->record->owner_name.'.'"
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
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500"
            >
                {{ $this->editingFile === null ? 'Add file' : 'Save changes' }}
            </button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal
        id="file-delete"
        title="Delete file?"
        message="&#8212; this cannot be undone."
        action="deleteFile"
        confirm-label="Delete file"
    />

    <x-confirm-modal
        id="record-delete"
        title="Delete record?"
        message="&#8212; this deletes it and all of its files."
        action="deleteRecord"
        confirm-label="Delete record"
    />
</div>
