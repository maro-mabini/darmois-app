<?php

namespace App\Models;

use Database\Factories\PlotterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title_number
 * @property string $lot_number
 * @property string $survey_number
 * @property string|null $title_name
 * @property string|null $area
 * @property string|null $location
 * @property string $raw_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title_number', 'lot_number', 'survey_number', 'title_name', 'area', 'location', 'raw_text'])]
class Plotter extends Model
{
    /** @use HasFactory<PlotterFactory> */
    use HasFactory;

    /**
     * Read the technical description into tie lines.
     *
     * Two shapes of entry are read, each taken one line at a time in the order
     * they appear on the page:
     *
     *     LINE: 1, BEARING: S. 57 degree, E, DISTANCE: 2360 M.
     *     0-1 S. 57 48 E 2360,42 M
     *
     * The first is the spelled-out form, matched loosely: the line number is
     * optional, commas are optional, and the bearing may spell out "degree",
     * use a degree sign, or carry minutes and seconds. The bearing is captured
     * up to the DISTANCE keyword rather than up to the next comma, because the
     * comma before the E/W letter belongs to the bearing itself.
     *
     * The second is how a surveyor writes a tie point: a corner pair, a bearing
     * in degrees and minutes, and a distance, with no keywords at all. A row is
     * tried as the spelled-out form first, so a description carrying the
     * keywords never has its bearing read by the other pattern.
     *
     * Entries whose bearing or distance cannot be read are dropped;
     * tieLines() returns only the lines the plot can be built from, while
     * declaredLineCount() reports how many the description claimed to hold.
     *
     * @return array<int, array{number: int, bearing: string, azimuth: float, distance: float, unit: string}>
     */
    public function tieLines(): array
    {
        $lines = [];
        $position = 0;

        foreach (preg_split('/\R/u', $this->raw_text) ?: [] as $row) {
            if (trim($row) === '') {
                continue;
            }

            $entries = $this->keywordEntries($row);

            if ($entries === []) {
                $entries = $this->cornerEntries($row);
            }

            foreach ($entries as $entry) {
                $position++;

                $azimuth = $this->azimuth($entry['bearing']);
                $distance = $this->distance($entry['distance']);

                if ($azimuth === null || $distance === null) {
                    continue;
                }

                $lines[] = [
                    'number' => $entry['number'] ?? $position,
                    'bearing' => $entry['bearing'],
                    'azimuth' => $azimuth,
                    'distance' => $distance,
                    'unit' => $entry['unit'] !== '' ? $entry['unit'] : 'M',
                ];
            }
        }

        return $lines;
    }

    /**
     * Walk the tie lines into the corners of the parcel, in survey units.
     *
     * x is east and y is north, so the shape can be handed to any drawing
     * format as it stands -- the SVG flips y because its axis points down.
     * The first corner is the origin and is repeated by the walk only when the
     * description closes on itself, so `points` holds one entry per tie line
     * plus the start. `gap` is the distance between the last corner and the
     * first, measured in the description's own units.
     *
     * @return array{points: non-empty-list<array{x: float, y: float}>, gap: float, closed: bool}
     */
    public function corners(): array
    {
        /** @var non-empty-list<array{x: float, y: float}> $points */
        $points = [['x' => 0.0, 'y' => 0.0]];
        $x = 0.0;
        $y = 0.0;

        foreach ($this->tieLines() as $line) {
            $bearing = deg2rad($line['azimuth']);

            $x += $line['distance'] * sin($bearing);
            $y += $line['distance'] * cos($bearing);

            $points[] = ['x' => $x, 'y' => $y];
        }

        $last = $points[array_key_last($points)];
        $gap = hypot($last['x'] - $points[0]['x'], $last['y'] - $points[0]['y']);

        return [
            'points' => $points,
            'gap' => $gap,
            // Anything under half a unit is the floating point of a figure that
            // does close, and is treated as closed.
            'closed' => $gap <= 0.005,
        ];
    }

    /**
     * The tie lines on one row written with the BEARING and DISTANCE keywords.
     *
     * @return array<int, array{number: int|null, bearing: string, distance: string, unit: string}>
     */
    protected function keywordEntries(string $row): array
    {
        $pattern = '/(?:LINE\s*:?\s*(\d+)\s*,?\s*)?BEARING\s*:?\s*(.+?)\s*,?\s*DISTANCE\s*:?\s*([\d.,]+)\s*([A-Za-z°]*)/iu';

        preg_match_all($pattern, $row, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [
            'number' => $match[1] !== '' ? (int) $match[1] : null,
            'bearing' => trim($match[2]),
            'distance' => $match[3],
            'unit' => trim($match[4]),
        ], $matches);
    }

    /**
     * The tie lines on one row written as a corner pair, bearing and distance:
     *
     *     0-1 S. 57 48 E 2360,42 M
     *
     * Degree and minute marks are swapped for spaces first, so the pattern can
     * ask for numbers separated by whitespace and still read 57° 48'. A due
     * course -- "0-2 DUE EAST 24.57" -- is the other bearing this accepts. The
     * bearing must end in its E/W letter before the distance begins, which is
     * what keeps a spelled-out bearing like "S. 57 degree, E" out of here.
     *
     * @return array<int, array{number: int|null, bearing: string, distance: string, unit: string}>
     */
    protected function cornerEntries(string $row): array
    {
        $row = (string) preg_replace('/[\x{00B0}\x{2019}\'"]+/u', ' ', $row);

        $pattern = '/(?:\d+\s*-\s*\d+\s+)?((?:DUE\s+(?:NORTH|SOUTH|EAST|WEST|[NSEW]))|(?:[NS]\.?\s*\d+(?:[.,]\d+)?(?:\s+\d+){0,2}\s*[EW]))\s+([\d.,]+)\s*([A-Za-z°]*)/iu';

        preg_match_all($pattern, $row, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [
            'number' => null,
            'bearing' => trim($match[1]),
            'distance' => $match[2],
            'unit' => trim($match[3]),
        ], $matches);
    }

    /**
     * Read a distance written with either a dot or a comma for the decimal
     * mark.
     *
     * Descriptions mix the two -- "10.78 M" and "2360,42 M" are the same unit
     * of measure -- while "2,360" is two thousand three hundred sixty. The
     * digits after a comma decide it: groups of three behind a one to three
     * digit head is a thousands separator, and anything else is a decimal point.
     *
     * Returns null when the value carries no digits at all, which drops the
     * entry instead of plotting it as a line of zero length.
     */
    protected function distance(string $value): ?float
    {
        $value = trim($value, " \t.,\n");

        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        $dot = strrpos($value, '.');
        $comma = strrpos($value, ',');

        if ($dot !== false && $comma !== false) {
            // Both marks present: whichever comes last is the decimal one.
            $mark = max($dot, $comma);
            $value = str_replace(['.', ','], '', substr($value, 0, $mark))
                .substr($value, $mark, 1)
                .substr($value, $mark + 1);
        } elseif ($comma !== false) {
            $value = preg_match('/^\d{1,3}(,\d{3})+$/', $value) === 1
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }

        return (float) $value;
    }

    /**
     * How many tie lines the description claims to hold.
     *
     * Counted from the line numbers and the corner pairs written on the page,
     * so the gap against tieLines() is how many entries were written but could
     * not be read.
     */
    public function declaredLineCount(): int
    {
        $keywordLines = (int) preg_match_all('/LINE\s*:\s*\d+/iu', $this->raw_text);
        $cornerLines = (int) preg_match_all('/^\s*\d+\s*-\s*\d+\s+\S/mu', $this->raw_text);

        return $keywordLines + $cornerLines;
    }

    /**
     * Turn a compass bearing into an azimuth in degrees clockwise from north.
     *
     * Accepts "S. 57 degree, E", "S 57-30 E", "N45W", "SE 30" and a due
     * course such as "DUE EAST" (or "DUE E"). Returns null when the quadrant
     * or the angle is missing, which leaves the entry out of the plot rather
     * than guessing a direction for it.
     */
    protected function azimuth(string $bearing): ?float
    {
        // A due course carries no quadrant of its own: "DUE EAST" is the whole
        // bearing, so it is resolved before the letters are rewritten below --
        // "DUE EAST" rewritten would keep the E and lose the north/south half.
        if (preg_match('/\bDUE\s+(?<cardinal>NORTH|SOUTH|EAST|WEST|[NSEW])/iu', trim($bearing), $due) === 1) {
            return match (substr(strtoupper($due['cardinal']), 0, 1)) {
                'N' => 0.0,
                'E' => 90.0,
                'S' => 180.0,
                default => 270.0,
            };
        }

        $text = (string) preg_replace(
            ['/\bNORTH\b/i', '/\bSOUTH\b/i', '/\bEAST\b/i', '/\bWEST\b/i', '/\bDEGREES?\b|\x{00B0}/iu'],
            ['N', 'S', 'E', 'W', ' '],
            $bearing,
        );

        $text = strtoupper($text);

        if (! preg_match('/([NS])/', $text, $northSouth) || ! preg_match('/([EW])/', $text, $eastWest)) {
            return null;
        }

        preg_match_all('/\d+(?:\.\d+)?/', $text, $numbers);

        if ($numbers[0] === []) {
            return null;
        }

        $angle = (float) $numbers[0][0];

        if (isset($numbers[0][1])) {
            $angle += (float) $numbers[0][1] / 60;
        }

        if (isset($numbers[0][2])) {
            $angle += (float) $numbers[0][2] / 3600;
        }

        // The two letters are each one of two, so these are the only quadrants
        // that can reach here: the early return above has already rejected a
        // bearing without one.
        $azimuth = match ($northSouth[1].$eastWest[1]) {
            'NE' => $angle,
            'NW' => 360 - $angle,
            'SE' => 180 - $angle,
            default => 180 + $angle,
        };

        return fmod($azimuth + 360, 360);
    }
}
