<?php

use App\Models\File;
use App\Models\RecordsOwner;

test('belongs to a record owner', function () {
    $owner = RecordsOwner::factory()->arb()->create();

    $file = $owner->files()->create([
        'file_name' => 'transfer-deed.pdf',
        'file_description' => 'Signed transfer deed.',
        'date_of_file' => '2026-01-15',
    ]);

    expect($file->recordsOwner->is($owner))->toBeTrue()
        ->and($file->date_of_file->toDateString())->toBe('2026-01-15')
        ->and($owner->files()->count())->toBe(1);
});

test('deletes its files when the record owner is deleted', function () {
    $owner = RecordsOwner::factory()->create();

    File::factory()->for($owner)->count(2)->create();

    $owner->delete();

    expect(File::query()->count())->toBe(0);
});
