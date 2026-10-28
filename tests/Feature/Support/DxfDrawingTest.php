<?php

use App\Models\Plotter;
use App\Support\Drawing\DxfDrawing;

/**
 * A closed 100 metre square: every corner is known, so the drawing can be
 * asserted against coordinates rather than against its own output.
 */
function dxfSquare(): Plotter
{
    return Plotter::factory()->make([
        'title_number' => 'TCT-0001',
        'lot_number' => 'Lot 7',
        'survey_number' => 'PSD-00-123',
        'title_name' => 'Juan Dela Cruz',
        'area' => '10,000 sq. m.',
        'location' => 'Barangay San Isidro',
        'raw_text' => implode("\n", [
            'LINE: 1, BEARING: N. 90 degree, E, DISTANCE: 100 M.',
            'LINE: 2, BEARING: S. 0 degree, E, DISTANCE: 100 M.',
            'LINE: 3, BEARING: N. 90 degree, W, DISTANCE: 100 M.',
            'LINE: 4, BEARING: N. 0 degree, W, DISTANCE: 100 M.',
        ]),
    ]);
}

test('writes a drawing file a CAD application can open', function () {
    $dxf = DxfDrawing::fromPlotter(dxfSquare());

    expect($dxf)
        ->toContain("0\r\nSECTION\r\n2\r\nHEADER")
        ->toContain('AC1015')
        ->toContain("0\r\nSECTION\r\n2\r\nTABLES")
        ->toContain("0\r\nSECTION\r\n2\r\nENTITIES")
        ->toEndWith("0\r\nEOF\r\n");
});

test('draws the parcel as a closed boundary with a dimension on every side', function () {
    $dxf = DxfDrawing::fromPlotter(dxfSquare());

    expect($dxf)
        // Four corners, closed flag set.
        ->toContain("0\r\nLWPOLYLINE\r\n8\r\nBOUNDARY\r\n90\r\n4\r\n70\r\n1")
        // The square runs 100 units east, 100 south, 100 west and home again.
        ->toContain("10\r\n0\r\n20\r\n0")
        ->toContain("10\r\n100\r\n20\r\n0")
        ->toContain("10\r\n100\r\n20\r\n-100")
        ->toContain("10\r\n0\r\n20\r\n-100")
        // One label per tie line.
        ->toContain("1\r\n100 M")
        ->not->toContain('Does not close');
});

test('writes the parcel details into the title block', function () {
    $dxf = DxfDrawing::fromPlotter(dxfSquare());

    expect($dxf)
        ->toContain("1\r\nPLOT PLAN")
        ->toContain("1\r\nLot Lot 7, Survey PSD-00-123")
        ->toContain("1\r\nTitle No.: TCT-0001")
        ->toContain("1\r\nName on title: Juan Dela Cruz")
        ->toContain("1\r\nArea: 10,000 sq. m.")
        ->toContain("1\r\nLocation: Barangay San Isidro")
        ->toContain('Perimeter: 400 M');
});

test('reports a description that does not close on the drawing', function () {
    $plotter = Plotter::factory()->make([
        'raw_text' => 'LINE: 1, BEARING: N. 90 degree, E, DISTANCE: 100 M.',
    ]);

    $dxf = DxfDrawing::fromPlotter($plotter);

    // An open boundary is a polyline without the closed flag, and the gap is
    // written out rather than drawn as if it were a surveyed side.
    expect($dxf)
        ->toContain("0\r\nLWPOLYLINE\r\n8\r\nBOUNDARY\r\n90\r\n2\r\n70\r\n0")
        ->toContain('Does not close: 100 M apart');
});

test('names the file after the parcel', function () {
    expect(DxfDrawing::filename(dxfSquare()))->toBe('plot-tct-0001-lot-7.dxf');
});

test('refuses to draw a description with nothing in it', function () {
    DxfDrawing::fromPlotter(Plotter::factory()->make(['raw_text' => 'bounded by a stone wall']));
})->throws(InvalidArgumentException::class);
