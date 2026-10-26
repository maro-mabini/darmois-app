<?php

use App\Models\OwnerType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * The owner type being edited, or null when the form is creating one.
     */
    public ?int $editing = null;

    public string $name = '';

    /**
     * The owner types, with the number of records using each.
     *
     * @return LengthAwarePaginator<int, OwnerType>
     */
    #[Computed]
    public function ownerTypes()
    {
        return OwnerType::query()
            ->withCount('records')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Reset the form so it is ready to create a type.
     */
    public function startCreate(): void
    {
        $this->editing = null;
        $this->name = '';
        $this->resetValidation();
    }

    /**
     * Load an owner type into the form.
     */
    public function startEdit(int $ownerType): void
    {
        $type = OwnerType::query()->findOrFail($ownerType);

        $this->editing = $type->id;
        $this->name = $type->name;
        $this->resetValidation();
    }

    /**
     * Create or update the owner type behind the form.
     */
    public function save(): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $exception) {
            $this->dispatch('modal-open', 'owner-type-form');

            throw $exception;
        }

        $creating = $this->editing === null;

        $type = $creating
            ? new OwnerType
            : OwnerType::query()->findOrFail($this->editing);

        $type->name = $validated['name'];
        $type->save();

        $this->editing = null;
        $this->name = '';
        $this->resetValidation();

        $this->dispatch('modal-saved', 'owner-type-form');
        $this->dispatch('toast', message: $creating ? 'Owner type created.' : 'Owner type updated.');
    }

    /**
     * Delete an owner type that no record uses.
     *
     * A type in use is left alone: records point at it with a foreign key that
     * has no cascade, so removing it would orphan them.
     */
    public function delete(int $ownerType): void
    {
        $type = OwnerType::query()->findOrFail($ownerType);
        $inUse = $type->records()->count();

        if ($inUse > 0) {
            $this->addError('ownerTypes', "“{$type->name}” is used by {$inUse} records and cannot be deleted.");

            $this->reopenDeleteModal($type);

            return;
        }

        $type->delete();

        $this->dispatch('modal-saved', 'owner-type-delete');
        $this->dispatch('toast', message: "Owner type “{$type->name}” deleted.");
    }

    /**
     * Re-open the delete dialog after a refused delete.
     *
     * Whether a dialog is open is browser state, not something Blade renders,
     * so a re-render is not a guarantee it is still on screen. Dispatching the
     * open event again puts it back and keeps the reason next to the button
     * that caused it.
     */
    protected function reopenDeleteModal(OwnerType $type): void
    {
        $this->dispatch('modal-open', [
            'id' => 'owner-type-delete',
            'payload' => ['id' => $type->id, 'label' => $type->name],
        ]);
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
                Rule::unique('owner_types', 'name')->ignore($this->editing),
            ],
        ];
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Owner types
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                The types of owner a record can be filed under.
            </p>
        </div>

        <button
            type="button"
            x-on:click="$wire.startCreate().then(() => $dispatch('modal-open', 'owner-type-form'))"
            class="mt-4 inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500 sm:mt-0"
        >
            New owner type
        </button>
    </header>

    <div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Name</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Records</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-300">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($this->ownerTypes as $ownerType)
                    <tr wire:key="owner-type-{{ $ownerType->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-950">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {{ $ownerType->name }}
                        </td>
                        <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">
                            {{ $ownerType->records_count }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button
                                type="button"
                                x-on:click="$wire.startEdit({{ $ownerType->id }}).then(() => $dispatch('modal-open', 'owner-type-form'))"
                                class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                data-label="{{ $ownerType->name }}"
                                x-on:click="$dispatch('modal-open', { id: 'owner-type-delete', payload: { id: {{ $ownerType->id }}, label: $el.dataset.label } })"
                                @disabled($ownerType->records_count > 0)
                                class="ml-3 font-medium text-red-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline dark:text-red-400 dark:disabled:text-gray-600"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-16 text-center text-gray-500 dark:text-gray-300">
                            No owner types yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->ownerTypes->hasPages())
        <div class="mt-6">
            {{ $this->ownerTypes->links() }}
        </div>
    @endif

    <x-modal
        id="owner-type-form"
        :title="$this->editing === null ? 'New owner type' : 'Edit owner type'"
        description="Owner types group the records of a single kind of owner."
    >
        <form id="owner-type-form-element" wire:submit="save">
            <x-form.field label="Name" name="name" required>
                <x-form.input name="name" />
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
                form="owner-type-form-element"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
            >
                {{ $this->editing === null ? 'Create' : 'Save changes' }}
            </button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal
        id="owner-type-delete"
        title="Delete owner type?"
        message="&#8212; this cannot be undone."
        action="delete"
        error-key="ownerTypes"
        confirm-label="Delete owner type"
    />
</div>
