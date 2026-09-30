<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\RecordsOwner;
use Illuminate\Database\Seeder;

class RecordsOwnerSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        RecordsOwner::factory()
            ->count(24)
            ->has(File::factory()->count(3), 'files')
            ->create();
    }
}
