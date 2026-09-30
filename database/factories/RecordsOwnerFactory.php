<?php

namespace Database\Factories;

use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordsOwner>
 */
class RecordsOwnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_name' => fake()->name(),
            'owner_type_id' => fn (): int => $this->existingOrNewOwnerTypeId(),
            'record_type_id' => fn (): int => $this->existingOrNewRecordTypeId(),
        ];
    }

    /**
     * Resolve the owner type a generated record should point at.
     *
     * Reusing an existing owner type keeps seeded data realistic -- without
     * this every generated record would introduce a brand new owner type. An
     * owner type is only created when the table is empty, which is the case
     * when a test does not set one up itself.
     */
    private function existingOrNewOwnerTypeId(): int
    {
        $existingId = OwnerType::query()->inRandomOrder()->value('id');

        return $existingId !== null
            ? (int) $existingId
            : OwnerType::factory()->create()->id;
    }

    /**
     * Resolve the record type a generated record should point at.
     *
     * Reusing an existing record type keeps seeded data realistic -- without
     * this every generated record would introduce a brand new record type. A
     * record type is only created when the table is empty, which is the case
     * when a test does not set one up itself.
     */
    private function existingOrNewRecordTypeId(): int
    {
        $existingId = RecordType::query()->inRandomOrder()->value('id');

        return $existingId !== null
            ? (int) $existingId
            : RecordType::factory()->create()->id;
    }

    /**
     * Indicate the record owner is a natural person.
     */
    public function arb(): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_type_id' => $this->ownerTypeIdNamed('ARB'),
        ]);
    }

    /**
     * Indicate the record owner is a legal entity.
     */
    public function arbo(): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_type_id' => $this->ownerTypeIdNamed('ARBO'),
        ]);
    }

    /**
     * Indicate the record owner is of unknown ownership.
     */
    public function noneArb(): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_type_id' => $this->ownerTypeIdNamed('None ARB'),
        ]);
    }

    /**
     * Resolve the id of a named owner type, creating it when it is missing.
     */
    private function ownerTypeIdNamed(string $name): int
    {
        $existingId = OwnerType::query()->where('name', $name)->value('id');

        return $existingId !== null
            ? (int) $existingId
            : OwnerType::factory()->create(['name' => $name])->id;
    }
}
