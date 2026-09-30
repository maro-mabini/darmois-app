<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\RecordsOwner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'records_owners_id' => RecordsOwner::factory(),
            'file_name' => Str::slug(fake()->sentence(3)).'.pdf',
            'file_description' => fake()->sentence(),
            'date_of_file' => fake()->date(),
        ];
    }
}
