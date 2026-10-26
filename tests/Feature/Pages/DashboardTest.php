<?php

use App\Models\File;
use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Models\Station;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('the dashboard renders each summary block', function () {
    $location = Location::factory()->create(['name' => 'Steel Cabinet 1']);
    $station = Station::factory()->create(['name' => 'Front Desk']);
    $recordType = RecordType::factory()->create(['name' => 'DAR Clearance']);
    $ownerType = OwnerType::factory()->create(['name' => 'ARB']);

    $record = RecordsOwner::factory()->create([
        'owner_name' => 'Estrella G. Cagata',
        'location_id' => $location->id,
        'station_id' => $station->id,
        'record_type_id' => $recordType->id,
        'owner_type_id' => $ownerType->id,
    ]);

    File::factory()->for($record)->create();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('record on file')
        ->assertSee('Recent records')
        ->assertSee('By record type')
        ->assertSee('Added over time')
        ->assertSee('Filing points')
        ->assertSee('Estrella G. Cagata')
        ->assertSee('DAR Clearance')
        ->assertSee('Steel Cabinet 1')
        ->assertSee('Front Desk')
        ->assertSee(route('admin.records.show', $record), escape: false);
});

test('the recent records stop at the five newest', function () {
    $recordType = RecordType::factory()->create(['name' => 'Case File']);
    $ownerType = OwnerType::factory()->create(['name' => 'ARB']);

    foreach (range(1, 6) as $number) {
        RecordsOwner::factory()->create([
            'owner_name' => "Record Number {$number}",
            'record_type_id' => $recordType->id,
            'owner_type_id' => $ownerType->id,
        ]);
    }

    // Every record is saved in the same second, so the id breaks the tie and
    // the first one written is the one that falls off the list.
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Record Number 6')
        ->assertSee('Record Number 2')
        ->assertDontSee('Record Number 1');
});

test('the record type bars are scaled against the busiest type', function () {
    $clearance = RecordType::factory()->create(['name' => 'Clearance']);
    $caseFile = RecordType::factory()->create(['name' => 'Case File']);
    RecordType::factory()->create(['name' => 'Unused']);

    RecordsOwner::factory()->count(3)->create(['record_type_id' => $clearance->id]);
    RecordsOwner::factory()->create(['record_type_id' => $caseFile->id]);

    $breakdown = collect(Livewire::test('admin::-dashboard')->instance()->recordTypeBreakdown())
        ->keyBy('name');

    expect($breakdown['Clearance']['count'])->toBe(3)
        ->and($breakdown['Clearance']['share'])->toBe(100.0)
        ->and($breakdown['Case File']['share'])->toBe(33.3)
        ->and($breakdown['Unused']['count'])->toBe(0);
});

test('the activity chart counts the last six months', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));

    $august = RecordsOwner::factory()->create();
    $august->created_at = Carbon::parse('2026-08-04 09:00:00');
    $august->save();

    RecordsOwner::factory()->create();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Records added per month: May 0, Jun 0, Jul 0, Aug 1, Sep 0, Oct 1', escape: false)
        ->assertSee('2 in six months');
});

test('a dashboard with nothing in it explains itself', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('No records yet')
        ->assertSee('Nothing added this month yet')
        ->assertSee('No record types yet')
        ->assertSee('No locations yet')
        ->assertSee('No stations yet');
});
