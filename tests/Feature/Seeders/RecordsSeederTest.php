<?php

use App\Models\File;
use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Models\Station;
use Database\Seeders\RecordsSeeder;

test('it seeds the records, their files, and the lookups they name', function () {
    $this->seed(RecordsSeeder::class);

    expect(RecordsOwner::query()->count())->toBe(16)
        ->and(File::query()->count())->toBe(32)
        ->and(RecordType::query()->where('name', 'LAND DISTRIBUTION FOLDER (LDF)')->exists())->toBeTrue()
        ->and(OwnerType::query()->where('name', 'LANDOWNER')->exists())->toBeTrue()
        ->and(Location::query()->where('name', 'CABINET 1')->exists())->toBeTrue()
        ->and(Station::query()->where('name', 'DARMO - MABINI')->exists())->toBeTrue();
});

test('it hangs each file on the record that owns it', function () {
    $this->seed(RecordsSeeder::class);

    $record = RecordsOwner::query()->where('owner_name', 'IDA A. ENTIZE')->sole();
    $albulario = RecordsOwner::query()->where('owner_name', 'CRESENCIANO ALBULARIO_02')->sole();

    expect($record->recordType->name)->toBe('DAR CLEARANCE')
        ->and($record->ownerType->name)->toBe('Non-ARB')
        ->and($record->location?->name)->toBe('CABINET 1')
        ->and($record->station?->name)->toBe('DARMO - MABINI')
        ->and($record->files()->count())->toBe(15)
        ->and($albulario->files()->sole()->file_description)->toBe("Attachment:\nCARP Form No. 19 with TCT/OCT No. (T-2682) T-688");
});

test('it adds nothing to a table that already holds the records', function () {
    $this->seed(RecordsSeeder::class);

    $records = RecordsOwner::query()->count();
    $files = File::query()->count();

    $this->seed(RecordsSeeder::class);

    expect(RecordsOwner::query()->count())->toBe($records)
        ->and(File::query()->count())->toBe($files)
        ->and(Location::query()->count())->toBe(1);
});
