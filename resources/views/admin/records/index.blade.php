<?php

use App\Models\OwnerType;
use App\Models\RecordType;
use App\Models\RecordsOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
     * The record being edited, or null when the form is creating one.
     */
    public ?int $editing = null;

    public string $owner_name = '';

    public string $owner_type_id = '';

    public string $record_type_id = '';

    /**
     * Free text matched against the owner name, owner type, and record type name.
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

        if ($this->editing === null) {
            RecordsOwner::create($validated);
        } else {
            RecordsOwner::query()->findOrFail($this->editing)->update($validated);
        }

        $this->editing = null;
        $this->owner_name = '';
        $this->owner_type_id = '';
        $this->record_type_id = '';
        $this->resetValidation();
        // The list is newest first, so a new record belongs on the first page.
        $this->resetPage();

        $this->dispatch('modal-saved', 'record-form');
    }

    /**
     * Delete a record and, through the database cascade, its files.
     */
    public function delete(int $record): void
    {
        RecordsOwner::query()->findOrFail($record)->delete();

        $this->dispatch('modal-saved', 'record-delete');
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
     * The record types offered by the filter dropdown.
     *
     * @return Collection<int, RecordType>
     */
    #[Computed]
    public function recordTypes(): Collection
    {
        return RecordType::query()->orderBy('name')->get();
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
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_type_id' => ['required', Rule::exists('owner_types', 'id')],
            'record_type_id' => ['required', Rule::exists('record_types', 'id')],
        ];
    }

    /**
     * The records matching the current filters, newest first, each with its file count.
     *
     * @return LengthAwarePaginator<int, RecordsOwner>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return RecordsOwner::query()
            ->with('recordType')
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
                        );
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
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500"
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
                placeholder="Search owner, type or record type"
                autocomplete="off"
                class="w-full rounded-lg border border-gray-300 bg-white py-2 pr-3 pl-9 text-sm text-gray-900 shadow-xs placeholder-gray-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-hidden [&::-webkit-search-cancel-button]:appearance-none dark:border-gray-700 dark:bg-white/5 dark:text-white dark:placeholder-gray-300"
            />
        </div>

        <div class="flex items-center gap-3">
            <label for="record-type-filter" class="sr-only">Filter by record type</label>

            <select
                id="record-type-filter"
                wire:model.live="recordType"
                class="w-full rounded-lg border border-gray-300 bg-white py-2 pr-8 pl-3 text-sm text-gray-900 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-hidden sm:w-56 dark:border-gray-700 dark:bg-white/5 dark:text-white"
            >
                <option value="">All record types</option>

                @foreach ($this->recordTypes as $recordTypeOption)
                    <option value="{{ $recordTypeOption->id }}">{{ $recordTypeOption->name }}</option>
                @endforeach
            </select>

            @if ($this->isFiltered)
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="shrink-0 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-300"
                >
                    Clear filters
                </button>
            @endif
        </div>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Owner</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Owner type</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Record type</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Files</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Added</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-300">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($this->records as $record)
                    <tr
                        wire:key="record-{{ $record->id }}"
                        x-data
                        x-on:click="if (! $event.target.closest('a, button')) Livewire.navigate(@js(route('admin.records.show', $record)))"
                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-950"
                    >
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            <a
                                href="{{ route('admin.records.show', $record) }}"
                                wire:navigate
                                class="hover:text-indigo-600 hover:underline dark:hover:text-indigo-300"
                            >
                                {{ $record->owner_name }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                {{ $record->ownerType->name }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                {{ $record->recordType->name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">
                            {{ $record->files_count }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            {{ $record->created_at->toFormattedDateString() }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button
                                type="button"
                                x-on:click="$wire.startEdit({{ $record->id }}).then(() => $dispatch('modal-open', 'record-form'))"
                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-300"
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
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-gray-500 dark:text-gray-300">
                            @if ($this->isFiltered)
                                No records match your search.
                                <button
                                    type="button"
                                    wire:click="clearFilters"
                                    class="font-medium text-indigo-600 hover:underline dark:text-indigo-300"
                                >
                                    Clear filters
                                </button>
                            @else
                                No records yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
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
                {{ $this->editing === null ? 'Create record' : 'Save changes' }}
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
