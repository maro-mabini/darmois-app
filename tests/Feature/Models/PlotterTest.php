<?php

use App\Models\Plotter;

test('reads the tie lines from a technical description', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => implode("\n", [
            'LINE: 1, BEARING: S. 57 degree, E, DISTANCE: 2360 M.',
            'LINE: 2, BEARING: N. 45 degree, W, DISTANCE: 100 M.',
        ]),
    ]);

    $lines = $plotter->tieLines();

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toMatchArray([
            'number' => 1,
            'bearing' => 'S. 57 degree, E',
            'azimuth' => 123.0,
            'distance' => 2360.0,
            'unit' => 'M',
        ])
        ->and($lines[1]['azimuth'])->toEqual(315.0);
});

test('reads minutes written with a dash or with spaces', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => implode("\n", [
            'LINE: 1, BEARING: N 45-30 E, DISTANCE: 100 M.',
            'LINE: 2, BEARING: S 57 30 E, DISTANCE: 100 M.',
        ]),
    ]);

    $lines = $plotter->tieLines();

    expect($lines[0]['azimuth'])->toEqual(45.5)
        ->and($lines[1]['azimuth'])->toEqual(122.5);
});

test('reads a bearing written with a degree sign and without a line number', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => 'BEARING: SE 30, DISTANCE: 50 M.',
    ]);

    $lines = $plotter->tieLines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toMatchArray([
            'number' => 1,
            'azimuth' => 150.0,
            'distance' => 50.0,
        ]);
});

test('reads distances written with a thousands separator', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => 'LINE: 1, BEARING: N. 90 degree, E, DISTANCE: 2,360 M.',
    ]);

    expect($plotter->tieLines()[0]['distance'])->toEqual(2360.0);
});

test('leaves out a line whose bearing has no direction in it', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => implode("\n", [
            'LINE: 1, BEARING: NORTH, DISTANCE: 50 M.',
            'LINE: 2, BEARING: N. 45 degree, E, DISTANCE: 100 M.',
        ]),
    ]);

    $lines = $plotter->tieLines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['number'])->toBe(2);
});

test('counts the tie lines the description claims to hold', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => implode("\n", [
            'LINE: 1, BEARING: NORTH, DISTANCE: 50 M.',
            'LINE: 2, BEARING: N. 45 degree, E, DISTANCE: 100 M.',
        ]),
    ]);

    expect($plotter->declaredLineCount())->toBe(2)
        ->and($plotter->tieLines())->toHaveCount(1);
});

test('reads tie points written as a corner pair with degrees and minutes', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => implode("\n", [
            '0-1 S. 57 48 E 2360,42 M',
            '1-2 N. 00 52 W 10.78 M',
            '2-3 N. 01 15 W 69.96 M.',
        ]),
    ]);

    $lines = $plotter->tieLines();

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toMatchArray([
            'bearing' => 'S. 57 48 E',
            'distance' => 2360.42,
            'unit' => 'M',
        ])
        ->and(round($lines[0]['azimuth'], 5))->toEqual(122.2)
        ->and($lines[1])->toMatchArray([
            'bearing' => 'N. 00 52 W',
            'distance' => 10.78,
        ])
        ->and(round($lines[1]['azimuth'], 4))->toEqual(359.1333)
        ->and($lines[2])->toMatchArray([
            'distance' => 69.96,
            'unit' => 'M',
        ])
        ->and(round($lines[2]['azimuth'], 5))->toEqual(358.75);
});

test('reads a corner pair written with degree and minute marks', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => '0-1 S. 57° 48\' E 100 M',
    ]);

    $lines = $plotter->tieLines();

    expect($lines)->toHaveCount(1)
        ->and(round($lines[0]['azimuth'], 5))->toEqual(122.2)
        ->and($lines[0]['distance'])->toEqual(100.0);
});

test('counts the corner pair lines the description claims to hold', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => implode("\n", [
            '0-1 S. 57 48 E 2360,42 M',
            '1-2 NORTH 10.78 M',
        ]),
    ]);

    expect($plotter->declaredLineCount())->toBe(2)
        ->and($plotter->tieLines())->toHaveCount(1);
});

test('reads a due course written as a corner pair', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => implode("\n", [
            '0-1 DUE EAST 24.57',
            '1-2 DUE NORTH 10 M',
            '2-3 DUE SOUTH 30 M',
            '3-4 DUE WEST 40 M',
            '4-5 DUE S 15 M',
        ]),
    ]);

    $lines = $plotter->tieLines();

    expect($lines)->toHaveCount(5)
        // No unit written, so the label falls back to metres.
        ->and($lines[0])->toMatchArray([
            'bearing' => 'DUE EAST',
            'distance' => 24.57,
            'unit' => 'M',
        ])
        ->and(round($lines[0]['azimuth'], 5))->toEqual(90.0)
        ->and(round($lines[1]['azimuth'], 5))->toEqual(0.0)
        ->and(round($lines[2]['azimuth'], 5))->toEqual(180.0)
        ->and(round($lines[3]['azimuth'], 5))->toEqual(270.0)
        ->and(round($lines[4]['azimuth'], 5))->toEqual(180.0);
});

test('reads a due course in the spelled out form', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => 'LINE: 1, BEARING: DUE WEST, DISTANCE: 100 M.',
    ]);

    $lines = $plotter->tieLines();

    expect($lines)->toHaveCount(1)
        ->and(round($lines[0]['azimuth'], 5))->toEqual(270.0)
        ->and($lines[0]['distance'])->toEqual(100.0);
});
