<?php

use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Models\Station;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('generates a distinct uuid for every record owner', function () {
    $first = RecordsOwner::factory()->create();
    $second = RecordsOwner::factory()->create();

    expect(Str::isUuid($first->uuid))->toBeTrue()
        ->and(Str::isUuid($second->uuid))->toBeTrue()
        ->and($second->uuid)->not->toBe($first->uuid);
});

test('keeps the incrementing id as the primary key', function () {
    $owner = RecordsOwner::factory()->create();

    expect($owner->getKeyName())->toBe('id')
        ->and($owner->getIncrementing())->toBeTrue()
        ->and($owner->getKeyType())->toBe('int');
});

test('belongs to an owner type', function () {
    $ownerType = OwnerType::factory()->create(['name' => 'Trust']);
    $owner = RecordsOwner::factory()->create(['owner_type_id' => $ownerType->id]);

    expect($owner->ownerType)->toBeInstanceOf(OwnerType::class)
        ->and($owner->ownerType->id)->toBe($ownerType->id)
        ->and($owner->fresh()->ownerType->name)->toBe('Trust');
});

test('belongs to a record type', function () {
    $recordType = RecordType::factory()->create();
    $owner = RecordsOwner::factory()->create(['record_type_id' => $recordType->id]);

    expect($owner->recordType)->toBeInstanceOf(RecordType::class)
        ->and($owner->recordType->id)->toBe($recordType->id)
        ->and($owner->fresh()->recordType->name)->toBe($recordType->name);
});

test('belongs to a location and a station', function () {
    $location = Location::factory()->create();
    $station = Station::factory()->create();

    $owner = RecordsOwner::factory()->create([
        'location_id' => $location->id,
        'station_id' => $station->id,
    ]);

    expect($owner->location)->toBeInstanceOf(Location::class)
        ->and($owner->location->name)->toBe($location->name)
        ->and($owner->station)->toBeInstanceOf(Station::class)
        ->and($owner->station->name)->toBe($station->name);
});

test('keeps a location and station optional', function () {
    $owner = RecordsOwner::factory()->create([
        'location_id' => null,
        'station_id' => null,
    ]);

    expect($owner->location)->toBeNull()
        ->and($owner->station)->toBeNull();
});

test('refuses an owner type id that does not exist', function () {
    RecordsOwner::factory()->create(['owner_type_id' => 9999]);
})->throws(QueryException::class);

test('refuses a record with no owner type at all', function () {
    DB::table('records_owners')->insert([
        'uuid' => (string) Str::uuid(),
        'owner_name' => 'No Type',
        'record_type_id' => RecordType::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
