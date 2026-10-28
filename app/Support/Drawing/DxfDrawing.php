<?php

namespace App\Support\Drawing;

use App\Models\Plotter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Draws a saved plotter as an ASCII DXF, the drawing exchange format opened by
 * AutoCAD, LibreCAD, QCAD, DraftSight and most other CAD applications.
 *
 * The file is written by hand rather than through a library because what the
 * plotter page already knows -- corners in survey units, one dimension per tie
 * line, the parcel's own metadata -- is almost exactly what a DXF needs. The
 * written flavour is AC1015 (AutoCAD 2000), which every reader in use accepts.
 */
class DxfDrawing
{
    private const NEWLINE = "\r\n";

    /**
     * Build the drawing of the given plotter.
     *
     * @throws InvalidArgumentException when the description yields no tie line to draw
     */
    public static function fromPlotter(Plotter $plotter): string
    {
        $lines = $plotter->tieLines();

        if ($lines === []) {
            throw new InvalidArgumentException('A drawing needs at least one tie line.');
        }

        $corners = $plotter->corners();
        $points = $corners['points'];
        $unit = $lines[0]['unit'];

        $minX = min(array_column($points, 'x'));
        $maxX = max(array_column($points, 'x'));
        $minY = min(array_column($points, 'y'));
        $maxY = max(array_column($points, 'y'));

        // A description of zero length still needs a scale to draw at.
        $span = max($maxX - $minX, $maxY - $minY, 1.0);

        $textHeight = round(max($span / 60, 0.05), 4);
        $lineHeight = $textHeight * 1.8;

        $boundary = self::closedBoundary($points, $corners['closed']);

        $entities = [self::polyline('BOUNDARY', $boundary, $corners['closed'])];

        // One dimension per tie line, written along the side it measures. The
        // angle is flipped into the half-turn where text still reads.
        foreach ($lines as $index => $line) {
            $from = $points[$index];
            $to = $points[$index + 1];

            $entities[] = self::text(
                layer: 'DIMENSION',
                x: ($from['x'] + $to['x']) / 2,
                y: ($from['y'] + $to['y']) / 2,
                height: $textHeight,
                value: self::number($line['distance']).' '.$line['unit'],
                rotation: self::readableAngle($from, $to),
            );
        }

        [$northArrow, $northTop] = self::northArrow($minX, $maxY, $span);

        array_push($entities, ...$northArrow);

        $title = self::titleLines($plotter, $lines, $corners['closed'], $corners['gap'], $unit);

        [$titleBlock, $titleBottom, $titleWidth] = self::titleBlock(
            $title,
            $minX,
            $minY,
            $span,
            $textHeight,
            $lineHeight,
        );

        array_push($entities, ...$titleBlock);

        $pieces = [
            ...self::section('HEADER', self::header(
                unit: $unit,
                minX: $minX,
                maxX: max($maxX, $minX + $titleWidth),
                minY: $titleBottom,
                maxY: max($maxY, $northTop),
            )),
            ...self::section('TABLES', self::tables()),
            ...self::section('BLOCKS', []),
            ...self::section('ENTITIES', array_merge(...$entities)),
        ];

        $pieces[] = '0';
        $pieces[] = 'EOF';

        return implode(self::NEWLINE, $pieces).self::NEWLINE;
    }

    /**
     * A file name a browser can save and a person can recognise.
     */
    public static function filename(Plotter $plotter): string
    {
        $slug = Str::slug('plot '.$plotter->title_number.' '.$plotter->lot_number);

        return ($slug !== '' ? $slug : 'plot').'.dxf';
    }

    /**
     * The boundary as vertices, without the closing point repeated: a closed
     * LWPOLYLINE already draws the last side back to the first.
     *
     * @param  array<int, array{x: float, y: float}>  $points
     * @return array<int, array{x: float, y: float}>
     */
    private static function closedBoundary(array $points, bool $closed): array
    {
        if ($closed && count($points) > 1) {
            array_pop($points);
        }

        return $points;
    }

    /**
     * The parcel's metadata, as the lines of the title block.
     *
     * @param  array<int, array{number: int, bearing: string, azimuth: float, distance: float, unit: string}>  $lines
     * @return array<int, string>
     */
    private static function titleLines(Plotter $plotter, array $lines, bool $closed, float $gap, string $unit): array
    {
        $titleName = trim((string) $plotter->title_name);
        $area = trim((string) $plotter->area);
        $location = trim((string) $plotter->location);

        return array_values(array_filter([
            'PLOT PLAN',
            'Lot '.$plotter->lot_number.', Survey '.$plotter->survey_number,
            'Title No.: '.$plotter->title_number,
            $titleName !== '' ? 'Name on title: '.$titleName : null,
            $area !== '' ? 'Area: '.$area : null,
            $location !== '' ? 'Location: '.$location : null,
            'Tie lines: '.count($lines).'    Perimeter: '.
                self::number(array_sum(array_column($lines, 'distance'))).' '.$unit,
            $closed ? null : 'Does not close: '.self::number($gap).' '.$unit.' apart',
        ], static fn (?string $line): bool => $line !== null && trim($line) !== ''));
    }

    /**
     * A bordered block of text sitting under the parcel.
     *
     * @param  array<int, string>  $title
     * @return array{0: array<int, array<int, string>>, 1: float, 2: float}
     */
    private static function titleBlock(
        array $title,
        float $minX,
        float $minY,
        float $span,
        float $textHeight,
        float $lineHeight,
    ): array {
        $padding = $textHeight * 1.2;
        $characterWidth = $textHeight * 0.62;

        $lengths = array_map(static fn (string $line): int => mb_strlen($line), $title);
        $longest = $lengths === [] ? 0 : max($lengths);
        $width = max($span, $longest * $characterWidth + 2 * $padding);
        $height = count($title) * $lineHeight + 2 * $padding;

        $top = $minY - $span * 0.08;
        $bottom = $top - $height;

        $border = self::polyline('TITLE', [
            ['x' => $minX, 'y' => $bottom],
            ['x' => $minX + $width, 'y' => $bottom],
            ['x' => $minX + $width, 'y' => $top],
            ['x' => $minX, 'y' => $top],
        ], true);

        $entities = [$border];

        foreach ($title as $index => $line) {
            $entities[] = self::text(
                layer: 'TITLE',
                x: $minX + $padding,
                y: $top - $padding - $lineHeight * ($index + 0.75),
                height: $textHeight,
                value: $line,
                horizontal: 0,
                vertical: 0,
            );
        }

        return [$entities, $bottom, $width];
    }

    /**
     * A north arrow standing off the top-left corner of the parcel.
     *
     * @return array{0: array<int, array<int, string>>, 1: float}
     */
    private static function northArrow(float $x, float $parcelTop, float $span): array
    {
        $bottom = $parcelTop + $span * 0.05;
        $top = $parcelTop + $span * 0.15;
        $head = $span * 0.025;

        $entities = [
            self::line($x, $bottom, $x, $top),
            self::line($x, $top, $x - $head, $top - $head * 1.6),
            self::line($x, $top, $x + $head, $top - $head * 1.6),
            self::text(
                layer: 'DIMENSION',
                x: $x,
                y: $top + $span * 0.045,
                height: $span / 45,
                value: 'N',
            ),
        ];

        return [$entities, $top + $span * 0.08];
    }

    /**
     * The HEADER section, which also tells the reader what the units are and
     * how far the drawing reaches.
     *
     * @return array<int, string>
     */
    private static function header(string $unit, float $minX, float $maxX, float $minY, float $maxY): array
    {
        return [
            '9', '$ACADVER', '1', 'AC1015',
            '9', '$INSUNITS', '70', self::unitsCode($unit),
            '9', '$MEASUREMENT', '70', self::unitsCode($unit) === '2' ? '0' : '1',
            '9', '$LUNITS', '70', '2',
            '9', '$EXTMIN', '10', self::number($minX), '20', self::number($minY), '30', '0.0',
            '9', '$EXTMAX', '10', self::number($maxX), '20', self::number($maxY), '30', '0.0',
        ];
    }

    /**
     * The tables a reader needs to resolve the layers and text style used
     * below.
     *
     * @return array<int, string>
     */
    private static function tables(): array
    {
        return [
            '0', 'TABLE', '2', 'LTYPE', '70', '1',
            '0', 'LTYPE', '2', 'CONTINUOUS', '70', '0', '3', 'Solid line', '72', '65', '73', '0', '40', '0.0',
            '0', 'ENDTAB',

            '0', 'TABLE', '2', 'LAYER', '70', '4',
            ...self::layer('0', 7),
            ...self::layer('BOUNDARY', 7),
            ...self::layer('DIMENSION', 3),
            ...self::layer('TITLE', 7),
            '0', 'ENDTAB',

            '0', 'TABLE', '2', 'STYLE', '70', '1',
            '0', 'STYLE', '2', 'STANDARD', '70', '0', '40', '0.0', '41', '1.0',
            '50', '0.0', '71', '0', '42', '2.5', '3', 'txt', '4', '',
            '0', 'ENDTAB',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function layer(string $name, int $colour): array
    {
        return ['0', 'LAYER', '2', $name, '70', '0', '62', (string) $colour, '6', 'CONTINUOUS'];
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     * @return array<int, string>
     */
    private static function polyline(string $layer, array $points, bool $closed): array
    {
        $entities = [
            '0', 'LWPOLYLINE',
            '8', $layer,
            '90', (string) count($points),
            '70', $closed ? '1' : '0',
            '43', '0.0',
        ];

        foreach ($points as $point) {
            $entities[] = '10';
            $entities[] = self::number($point['x']);
            $entities[] = '20';
            $entities[] = self::number($point['y']);
        }

        return $entities;
    }

    /**
     * @return array<int, string>
     */
    private static function line(float $x1, float $y1, float $x2, float $y2): array
    {
        return [
            '0', 'LINE', '8', 'BOUNDARY',
            '10', self::number($x1), '20', self::number($y1), '30', '0.0',
            '11', self::number($x2), '21', self::number($y2), '31', '0.0',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function text(
        string $layer,
        float $x,
        float $y,
        float $height,
        string $value,
        float $rotation = 0.0,
        int $horizontal = 1,
        int $vertical = 2,
    ): array {
        return [
            '0', 'TEXT',
            '8', $layer,
            '10', self::number($x), '20', self::number($y), '30', '0.0',
            '40', self::number($height),
            '1', self::sanitize($value),
            '50', self::number($rotation),
            '72', (string) $horizontal,
            '73', (string) $vertical,
            '11', self::number($x), '21', self::number($y), '31', '0.0',
        ];
    }

    /**
     * @param  array<int, string>  $body
     * @return array<int, string>
     */
    private static function section(string $name, array $body): array
    {
        return ['0', 'SECTION', '2', $name, ...$body, '0', 'ENDSEC'];
    }

    /**
     * The rotation that keeps a dimension label the right way up.
     *
     * @param  array{x: float, y: float}  $from
     * @param  array{x: float, y: float}  $to
     */
    private static function readableAngle(array $from, array $to): float
    {
        $angle = rad2deg(atan2($to['y'] - $from['y'], $to['x'] - $from['x']));

        if ($angle > 90) {
            $angle -= 180;
        } elseif ($angle < -90) {
            $angle += 180;
        }

        return round($angle, 2);
    }

    /**
     * Survey units as a DXF $INSUNITS code: feet when the description measures
     * in feet, metres for everything else.
     */
    private static function unitsCode(string $unit): string
    {
        return preg_match('/F(T|EET)?\b/i', $unit) === 1 ? '2' : '6';
    }

    /**
     * A number with a dot decimal mark and no trailing zeros.
     */
    private static function number(float $value): string
    {
        $value = round($value, 4);

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * A DXF text value is a single line: the reader takes the rest of the file
     * for the next group code if the value carries a line break.
     */
    private static function sanitize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
