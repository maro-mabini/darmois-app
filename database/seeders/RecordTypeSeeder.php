<?php

namespace Database\Seeders;

use App\Models\RecordType;
use Illuminate\Database\Seeder;

class RecordTypeSeeder extends Seeder
{
    /**
     * The record types the application shipped with before record types became
     * user manageable.
     *
     * @var list<string>
     */
    private const DEFAULT_RECORD_TYPES = [
        'Case File',
        'Profile',
        'DAR Clearance',
        'Approval of Transfer',
    ];

    /**
     * Seed the application's default record types.
     */
    public function run(): void
    {
        foreach (self::DEFAULT_RECORD_TYPES as $name) {
            RecordType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
