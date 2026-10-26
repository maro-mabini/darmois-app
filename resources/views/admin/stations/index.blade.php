<?php

use App\Models\Station;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * The station being edited, or null when the form is creating one.
     */
    public ?int $editing = null;

    public string $name = '';

    public string $description = '';

    /**
     * The stations, with the number of records each handles.
     *
     * @return LengthAwarePaginator<int, Station>
     */
    #[Computed]
    public function stations()
    {
        return Station::query()
            ->withCount('records')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Reset the form so it is ready to create a station.
     */
    public function startCreate(): void
    {
        $this->editing = null;
        $this->name = '';
        $this->description = '';
        $this->resetValidation();
    }

    /**
     * Load a station into the form.
     */
    public function startEdit(int $station): void
    {
        $model = Station::query()->findOrFail($station);

        $this->editing = $model->id;
        $this->name = $model->name;
        $this->description = (string) $model->description;
        $this->resetValidation();
    }

    /**
     * Create or update the station behind the form.
     */
    public function save(): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $exception) {
            $this->dispatch('modal-open', 'station-form');

            throw $exception;
        }

        $creating = $this->editing === null;

        $station = $creating
            ? new Station
            : Station::query()->findOrFail($this->editing);

        $station->name = $validated['name'];
        $station->description = $validated['description'] !== '' ? $validated['description'] : null;
        $station->save();

        $this->editing = null;
        $this->name = '';
        $this->description = '';
        $this->resetValidation();

        $this->dispatch('modal-saved', 'station-form');
        $this->dispatch('toast', message: $creating ? 'Station created.' : 'Station updated.');
    }

    /**
     * Delete a station that no record points at.
     *
     * A station in use is left alone: records point at it with a foreign key
     * that has no cascade, so removing it would orphan them.
     */
    public function delete(int $station): void
    {
        $model = Station::query()->findOrFail($station);
        $inUse = $model->records()->count();

        if ($inUse > 0) {
            $this->addError('stations', "“{$model->name}” is used by {$inUse} records and cannot be deleted.");

            $this->dispatch('modal-open', [
                'id' => 'station-delete',
                'payload' => ['id' => $model->id, 'label' => $model->name],
            ]);

            return;
        }

        $model->delete();

        $this->dispatch('modal-saved', 'station-delete');
        $this->dispatch('toast', message: "Station “{$model->name}” deleted.");
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stations', 'name')->ignore($this->editing),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Stations
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                The DAR stations that handle records.
            </p>
        </div>

        <button
            type="button"
            x-on:click="$wire.startCreate().then(() => $dispatch('modal-open', 'station-form'))"
            class="mt-4 inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500 sm:mt-0"
        >
            New station
        </button>
    </header>

    <div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Name</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Description</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Records</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-300">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($this->stations as $station)
                    <tr wire:key="station-{{ $station->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-950">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {{ $station->name }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                            {{ $station->description ? Str::limit($station->description, 90) : '—' }}
                        </td>
                        <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">
                            {{ $station->records_count }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button
                                type="button"
                                x-on:click="$wire.startEdit({{ $station->id }}).then(() => $dispatch('modal-open', 'station-form'))"
                                class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                data-label="{{ $station->name }}"
                                x-on:click="$dispatch('modal-open', { id: 'station-delete', payload: { id: {{ $station->id }}, label: $el.dataset.label } })"
                                @disabled($station->records_count > 0)
                                class="ml-3 font-medium text-red-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline dark:text-red-400 dark:disabled:text-gray-600"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-16 text-center text-gray-500 dark:text-gray-300">
                            No stations yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->stations->hasPages())
        <div class="mt-6">
            {{ $this->stations->links() }}
        </div>
    @endif

    <x-modal
        id="station-form"
        :title="$this->editing === null ? 'New station' : 'Edit station'"
        description="Stations are the DAR offices that handle records."
    >
        <form id="station-form-element" wire:submit="save" class="space-y-5">
            <x-form.field label="Name" name="name" required>
                <x-form.input name="name" autocomplete="off" placeholder="e.g. DAR Station I" />
            </x-form.field>

            <x-form.field label="Description" name="description">
                <x-form.textarea name="description" rows="3" />
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
                form="station-form-element"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
            >
                {{ $this->editing === null ? 'Create' : 'Save changes' }}
            </button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal
        id="station-delete"
        title="Delete station?"
        message="&#8212; this cannot be undone."
        action="delete"
        error-key="stations"
        confirm-label="Delete station"
    />
</div>
