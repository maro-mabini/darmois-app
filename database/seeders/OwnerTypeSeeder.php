<?php

namespace Database\Seeders;

use App\Models\OwnerType;
use Illuminate\Database\Seeder;

class OwnerTypeSeeder extends Seeder
{
    /**
     * The owner types the application shipped with before owner types became
     * user manageable.
     *
     * @var list<string>
     */
    private const DEFAULT_OWNER_TYPES = [
        'ARB',
        'ARBO',
        'None ARB',
    ];

    /**
     * Seed the application's default owner types.
     */
    public function run(): void
    {
        foreach (self::DEFAULT_OWNER_TYPES as $name) {
            OwnerType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
