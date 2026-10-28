<?php

use App\Models\Plotter;
use App\Support\Drawing\DxfDrawing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component {
    use WithPagination;

    /**
     * The plotter behind the form, or null while a new one is being written.
     */
    public ?int $editing = null;

    public string $title_number = '';

    public string $lot_number = '';

    public string $survey_number = '';

    public string $title_name = '';

    public string $area = '';

    public string $location = '';

    public string $raw_text = '';

    /**
     * Free text matched against the title number, title name, lot, survey and
     * location of the saved plotters.
     */
    #[Url(as: 'search', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Drop the search and return to the first page.
     */
    public function clearFilters(): void
    {
        $this->reset('search');
    }

    /**
     * Whether anything is currently narrowing the list.
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->searchTerm() !== '';
    }

    /**
     * The saved plotters matching the search, newest first.
     *
     * @return LengthAwarePaginator<int, Plotter>
     */
    #[Computed]
    public function plotters()
    {
        return Plotter::query()
            ->when($this->searchTerm() !== '', function (Builder $query) {
                $term = '%'.$this->likePattern($this->searchTerm()).'%';

                $query->where(function (Builder $query) use ($term) {
                    $query->whereRaw("title_number LIKE ? ESCAPE '\\'", [$term])
                        ->orWhereRaw("title_name LIKE ? ESCAPE '\\'", [$term])
                        ->orWhereRaw("lot_number LIKE ? ESCAPE '\\'", [$term])
                        ->orWhereRaw("survey_number LIKE ? ESCAPE '\\'", [$term])
                        ->orWhereRaw("location LIKE ? ESCAPE '\\'", [$term]);
                });
            })
            ->latest('id')
            ->paginate(15);
    }

    /**
     * The description as it stands in the form, as a model ready to parse it.
     *
     * The parsing lives on the model so the plot drawn from unsaved text and
     * the plot drawn from a saved row are read by exactly the same code.
     */
    protected function draft(): Plotter
    {
        return new Plotter(['raw_text' => $this->raw_text]);
    }

    /**
     * The tie lines the current description yields.
     *
     * @return array<int, array{number: int, bearing: string, azimuth: float, distance: float, unit: string}>
     */
    #[Computed]
    public function tieLines(): array
    {
        return $this->draft()->tieLines();
    }

    /**
     * The description scaled into an 800 by 560 frame for the SVG.
     *
     * The tie lines are walked from the first corner to build the outline in
     * survey units (north is up, so the SVG's downward y is negated), then the
     * whole figure is fitted into the frame with one scale on both axes, which
     * keeps the shape honest instead of stretching it to fill the panel.
     *
     * @return array{
     *     polygon: string,
     *     points: array<int, array{x: float, y: float}>,
     *     segments: array<int, array{from: array{x: float, y: float}, to: array{x: float, y: float}, mid: array{x: float, y: float}, angle: float, label: string, number: int}>,
     *     closure: array{x1: float, y1: float, x2: float, y2: float, mx: float, my: float, label: string}|null,
     *     unit: string,
     *     lineCount: int,
     *     declaredCount: int
     * }|null
     */
    #[Computed]
    public function plot(): ?array
    {
        $lines = $this->tieLines;

        if ($lines === []) {
            return null;
        }

        $width = 800;
        $height = 560;
        $padding = 80;

        $corners = $this->draft()->corners();

        // SVG's y axis points down, so the survey's north-up y is flipped here
        // rather than in the model, which hands out coordinates a CAD file can
        // use as they are.
        /** @var array<int, array{x: float, y: float}> $world */
        $world = array_map(
            static fn (array $point): array => ['x' => $point['x'], 'y' => -$point['y']],
            $corners['points'],
        );

        $minX = min(array_column($world, 'x'));
        $maxX = max(array_column($world, 'x'));
        $minY = min(array_column($world, 'y'));
        $maxY = max(array_column($world, 'y'));

        // Floored so a description of lines with no length still has a frame
        // to render in rather than dividing the scale by zero.
        $spanX = max($maxX - $minX, 1e-9);
        $spanY = max($maxY - $minY, 1e-9);
        $scale = min(($width - 2 * $padding) / $spanX, ($height - 2 * $padding) / $spanY);

        $offsetX = ($width - $spanX * $scale) / 2 - $minX * $scale;
        $offsetY = ($height - $spanY * $scale) / 2 - $minY * $scale;

        $points = [];

        foreach ($world as $point) {
            $points[] = [
                'x' => round($point['x'] * $scale + $offsetX, 2),
                'y' => round($point['y'] * $scale + $offsetY, 2),
            ];
        }

        $segments = [];

        foreach ($lines as $index => $line) {
            $from = $points[$index];
            $to = $points[$index + 1];

            $angle = rad2deg(atan2($to['y'] - $from['y'], $to['x'] - $from['x']));

            // A line running leftwards would set its label upside down, so the
            // angle is flipped into the half-turn where text still reads.
            if ($angle > 90) {
                $angle -= 180;
            } elseif ($angle < -90) {
                $angle += 180;
            }

            $segments[] = [
                'from' => $from,
                'to' => $to,
                'mid' => [
                    'x' => round(($from['x'] + $to['x']) / 2, 2),
                    'y' => round(($from['y'] + $to['y']) / 2, 2),
                ],
                'angle' => round($angle, 2),
                'label' => $this->formatDistance($line['distance']).' '.$line['unit'],
                'number' => $line['number'],
            ];
        }

        // The gap between the last corner and the first is measured in survey
        // units, not in pixels, because it is reported to the user as a distance.
        // The model decides whether it counts as closed.
        $gap = $corners['gap'];

        $first = $points[0];
        $last = $points[array_key_last($points)];

        $closure = $gap > 0.005
            ? [
                'x1' => $last['x'],
                'y1' => $last['y'],
                'x2' => $first['x'],
                'y2' => $first['y'],
                'mx' => round(($last['x'] + $first['x']) / 2, 2),
                'my' => round(($last['y'] + $first['y']) / 2, 2),
                'label' => $this->formatDistance($gap).' '.$lines[0]['unit'],
            ]
            : null;

        return [
            'polygon' => implode(' ', array_map(
                static fn (array $point): string => $point['x'].','.$point['y'],
                $points,
            )),
            'points' => $points,
            'segments' => $segments,
            'closure' => $closure,
            'unit' => $lines[0]['unit'],
            'lineCount' => count($lines),
            'declaredCount' => max(count($lines), $this->draft()->declaredLineCount()),
        ];
    }

    /**
     * Start a blank description.
     */
    public function startCreate(): void
    {
        $this->editing = null;
        $this->title_number = '';
        $this->lot_number = '';
        $this->survey_number = '';
        $this->title_name = '';
        $this->area = '';
        $this->location = '';
        $this->raw_text = '';
        $this->resetValidation();
    }

    /**
     * Load a saved plotter back into the form.
     */
    public function startEdit(int $plotter): void
    {
        $model = Plotter::query()->findOrFail($plotter);

        $this->editing = $model->id;
        $this->title_number = $model->title_number;
        $this->lot_number = $model->lot_number;
        $this->survey_number = $model->survey_number;
        $this->title_name = (string) ($model->title_name ?? '');
        $this->area = (string) ($model->area ?? '');
        $this->location = (string) ($model->location ?? '');
        $this->raw_text = $model->raw_text;
        $this->resetValidation();
    }

    /**
     * Create or update the plotter behind the form.
     *
     * A description that yields no tie lines is refused: a saved plotter that
     * cannot be plotted would sit in the list looking broken.
     */
    public function save(): void
    {
        $validated = $this->validate();

        if ($this->tieLines === []) {
            throw ValidationException::withMessages([
                'raw_text' => 'No tie lines could be read. Write them as LINE: 1, BEARING: S. 57 degree, E, DISTANCE: 2360 M.',
            ]);
        }

        $creating = $this->editing === null;

        $plotter = $creating
            ? new Plotter
            : Plotter::query()->findOrFail($this->editing);

        $plotter->title_number = (string) $validated['title_number'];
        $plotter->lot_number = (string) $validated['lot_number'];
        $plotter->survey_number = (string) $validated['survey_number'];
        $plotter->title_name = ($validated['title_name'] ?? '') !== '' ? (string) $validated['title_name'] : null;
        $plotter->area = ($validated['area'] ?? '') !== '' ? (string) $validated['area'] : null;
        $plotter->location = ($validated['location'] ?? '') !== '' ? (string) $validated['location'] : null;
        $plotter->raw_text = (string) $validated['raw_text'];
        $plotter->save();

        $this->editing = $plotter->id;

        $this->dispatch('toast', message: $creating ? 'Plotter saved.' : 'Plotter updated.');
    }

    /**
     * Delete a plotter.
     *
     * Nothing else points at a plotter, so unlike a location or a station
     * there is no in-use guard to hold the delete back.
     */
    public function delete(int $plotter): void
    {
        $model = Plotter::query()->findOrFail($plotter);

        $model->delete();

        if ($this->editing === $model->id) {
            $this->startCreate();
        }

        // The list has changed, so anything read before the delete is stale.
        unset($this->plotters);

        $plotters = $this->plotters;

        // Deleting the last row of a page would leave the list pointing at a
        // page that no longer exists, showing nothing while records sit on the
        // page before it.
        if ($plotters->isEmpty() && $plotters->currentPage() > 1) {
            $this->setPage($plotters->lastPage());
            unset($this->plotters);
        }

        $this->dispatch('modal-saved', 'plotter-delete');
        $this->dispatch('toast', message: "Plotter “{$model->title_number}” deleted.");
    }

    /**
     * Download the plot on screen as a DXF drawing.
     *
     * The form's values are drawn rather than a saved row, so what is
     * downloaded is what is being looked at. Nothing touches the disk: the
     * file is streamed straight back to the browser.
     */
    public function download(): ?StreamedResponse
    {
        if ($this->tieLines === []) {
            $this->dispatch('toast', message: 'There is nothing to download yet.');

            return null;
        }

        $plotter = new Plotter([
            'title_number' => $this->title_number,
            'lot_number' => $this->lot_number,
            'survey_number' => $this->survey_number,
            'title_name' => $this->title_name !== '' ? $this->title_name : null,
            'area' => $this->area !== '' ? $this->area : null,
            'location' => $this->location !== '' ? $this->location : null,
            'raw_text' => $this->raw_text,
        ]);

        return response()->streamDownload(
            static fn () => print DxfDrawing::fromPlotter($plotter),
            DxfDrawing::filename($plotter),
            ['Content-Type' => 'application/dxf'],
        );
    }

    /**
     * Highlight occurrences of the active search term inside the given text.
     */
    public function highlight(?string $text): HtmlString
    {
        if ($text === null || $text === '') {
            return new HtmlString('');
        }

        $term = $this->searchTerm();

        if ($term === '') {
            return new HtmlString(e($text));
        }

        $pattern = '/'.preg_quote($term, '/').'/i';

        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return new HtmlString(e($text));
        }

        $result = '';
        $lastOffset = 0;

        foreach ($matches[0] as $match) {
            $matchText = $match[0];
            $matchOffset = (int) $match[1];
            $matchLen = strlen($matchText);

            if ($matchOffset > $lastOffset) {
                $result .= e(substr($text, $lastOffset, $matchOffset - $lastOffset));
            }

            $result .= '<mark class="rounded-xs bg-amber-200 px-0.5 font-medium text-gray-900 dark:bg-amber-500/30 dark:text-amber-200">'.e($matchText).'</mark>';

            $lastOffset = $matchOffset + $matchLen;
        }

        if ($lastOffset < strlen($text)) {
            $result .= e(substr($text, $lastOffset));
        }

        return new HtmlString($result);
    }

    /**
     * The trimmed search term, or an empty string when the box holds only spaces.
     */
    private function searchTerm(): string
    {
        return trim($this->search);
    }

    /**
     * Escape LIKE wildcards so a literal % or _ is matched literally.
     *
     * The escape character is declared in the query rather than assumed: SQLite
     * treats a backslash as an ordinary character without an ESCAPE clause.
     */
    private function likePattern(string $term): string
    {
        return addcslashes($term, '\\%_');
    }

    /**
     * A distance without the trailing zeros a two-decimal format would add.
     */
    protected function formatDistance(float $distance): string
    {
        return rtrim(rtrim(number_format($distance, 2, '.', ','), '0'), '.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'title_number' => ['required', 'string', 'max:255'],
            'lot_number' => ['required', 'string', 'max:255'],
            'survey_number' => ['required', 'string', 'max:255'],
            'title_name' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'raw_text' => ['required', 'string', 'max:20000'],
        ];
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 print:max-w-none print:px-0 print:py-0">
    <header class="sm:flex sm:items-end sm:justify-between print:hidden">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Plotter
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                Read a technical description and draw the shape of the land, with each tie line's dimension on it.
            </p>
        </div>

        <button
            type="button"
            x-on:click="$wire.startCreate()"
            class="mt-4 inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500 sm:mt-0"
        >
            New plotter
        </button>
    </header>

    <div class="mt-8 grid gap-6 lg:grid-cols-5 print:mt-0 print:block">
        <x-panel
            title="Parcel"
            class="lg:col-span-2 print:hidden"
            :description="$editing === null ? 'The parcel about to be plotted.' : 'Editing saved plotter #'.$editing.'.'"
        >
            <form wire:submit="save" class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Title number" name="title_number" required>
                        <x-form.input name="title_number" autocomplete="off" placeholder="TCT-12345" />
                    </x-form.field>

                    <x-form.field label="Lot number" name="lot_number" required>
                        <x-form.input name="lot_number" autocomplete="off" placeholder="Lot 7" />
                    </x-form.field>

                    <x-form.field label="Survey number" name="survey_number" required>
                        <x-form.input name="survey_number" autocomplete="off" placeholder="PSD-00-12345" />
                    </x-form.field>

                    <x-form.field label="Area" name="area">
                        <x-form.input name="area" autocomplete="off" placeholder="1,200 sq. m." />
                    </x-form.field>
                </div>

                <x-form.field label="Name on title" name="title_name" hint="The registered owner written on the title, when the title carries one.">
                    <x-form.input name="title_name" autocomplete="off" placeholder="Juan Dela Cruz" />
                </x-form.field>

                <x-form.field label="Location" name="location">
                    <x-form.input name="location" autocomplete="off" placeholder="Barangay San Isidro" />
                </x-form.field>

                <x-form.field
                    label="Technical description"
                    name="raw_text"
                    required
                    hint="One tie line per line: 0-1 S. 57 48 E 2360,42 M, 0-2 DUE EAST 24.57, or spelled out, LINE: 1, BEARING: S. 57 degree, E, DISTANCE: 2360 M."
                >
                    <textarea
                        id="raw_text"
                        name="raw_text"
                        rows="12"
                        wire:model.live.debounce.400ms="raw_text"
                        x-on:keydown.enter="$wire.set('raw_text', $event.target.value)"
                        placeholder="0-1 S. 57 48 E 2360,42 M"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-sm text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600 focus:ring-inset dark:bg-white/5 dark:text-white dark:ring-gray-700 dark:placeholder:text-gray-300 dark:focus:ring-brand-500"
                    ></textarea>
                </x-form.field>

                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
                    >
                        {{ $editing === null ? 'Save plotter' : 'Save changes' }}
                    </button>

                    @if ($editing !== null)
                        <button
                            type="button"
                            x-on:click="$wire.startCreate()"
                            class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300"
                        >
                            Start a new one
                        </button>
                    @endif
                </div>
            </form>
        </x-panel>

        <x-panel title="Plot" class="plot-print lg:col-span-3 print:col-span-5 print:rounded-none print:border-0 print:shadow-none">
            @php($plot = $this->plot)

            {{--
                A clean heading for paper: the page title, the form and the panel's own header
                are hidden when printing, so the sheet opens with the parcel it describes.
            --}}
            <div class="mb-4 hidden print:block">
                <h1 class="text-lg font-semibold text-black">
                    Plot{{ $lot_number !== '' ? ' — Lot '.$lot_number : '' }}{{ $survey_number !== '' ? ', Survey '.$survey_number : '' }}
                </h1>

                @if ($title_number !== '' || $area !== '' || $location !== '')
                    <p class="mt-1 text-sm text-gray-700">
                        {{ implode(' · ', array_filter([$title_number, $area, $location])) }}
                    </p>
                @endif

                @if ($title_name !== '')
                    <p class="mt-1 text-sm text-gray-700">Name on title: {{ $title_name }}</p>
                @endif
            </div>

            {{--
                The viewer holds the zoom and pan state. It is Alpine all the way down: the
                numbers live here, are written to CSS custom properties on the document root
                (outside anything Livewire re-renders, so a keystroke cannot reset the view),
                and the drawing reads them back through one static transform on its group.
            --}}
            <div
                x-data="{
                    zoom: 1,
                    panX: 0,
                    panY: 0,
                    drag: null,
                    init() {
                        this.apply()
                    },
                    apply() {
                        const root = document.documentElement.style
                        root.setProperty('--plotter-zoom', String(this.zoom))
                        root.setProperty('--plotter-pan-x', this.panX + 'px')
                        root.setProperty('--plotter-pan-y', this.panY + 'px')
                    },
                    zoomLabel() {
                        return Math.round(this.zoom * 100) + '%'
                    },
                    clampPan() {
                        this.panX = Math.min(600, Math.max(200 - 800 * this.zoom, this.panX))
                        this.panY = Math.min(420, Math.max(140 - 560 * this.zoom, this.panY))
                    },
                    zoomAt(factor, el, clientX, clientY) {
                        const rect = el.getBoundingClientRect()
                        const fit = Math.min(rect.width / 800, rect.height / 560)
                        const viewX = ((clientX ?? rect.left + rect.width / 2) - rect.left - (rect.width - 800 * fit) / 2) / fit
                        const viewY = ((clientY ?? rect.top + rect.height / 2) - rect.top - (rect.height - 560 * fit) / 2) / fit
                        const zoom = Math.min(16, Math.max(0.25, this.zoom * factor))
                        this.panX = viewX - ((viewX - this.panX) / this.zoom) * zoom
                        this.panY = viewY - ((viewY - this.panY) / this.zoom) * zoom
                        this.zoom = zoom
                        this.clampPan()
                        this.apply()
                    },
                    wheel(event) {
                        this.zoomAt(event.deltaY < 0 ? 1.15 : 1 / 1.15, event.currentTarget, event.clientX, event.clientY)
                    },
                    fit() {
                        this.zoom = 1
                        this.panX = 0
                        this.panY = 0
                        this.apply()
                    },
                    startPan(event) {
                        if (event.button !== 0) return
                        this.drag = { x: event.clientX, y: event.clientY, panX: this.panX, panY: this.panY, el: event.currentTarget }
                    },
                    movePan(event) {
                        if (!this.drag) return
                        const rect = this.drag.el.getBoundingClientRect()
                        const fit = Math.min(rect.width / 800, rect.height / 560)
                        this.panX = this.drag.panX + (event.clientX - this.drag.x) / fit
                        this.panY = this.drag.panY + (event.clientY - this.drag.y) / fit
                        this.clampPan()
                        this.apply()
                    },
                    endPan() {
                        this.drag = null
                    },
                }"
                x-on:beforeprint.window="fit()"
            >
                @if ($plot)
                    {{-- Ignored so Livewire's re-render cannot overwrite the live zoom readout. --}}
                    <div
                        wire:ignore
                        class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-200 print:hidden dark:bg-white/5 dark:ring-gray-800"
                    >
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Hover the plot to see each side's dimension. Scroll to zoom, drag to pan, or use the buttons.
                        </p>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                x-on:click="zoomAt(1 / 1.3, $refs.drawing)"
                                aria-label="Zoom out"
                                class="rounded-md bg-white p-1.5 text-gray-700 shadow-xs ring-1 ring-gray-300 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-white/10"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                                </svg>
                            </button>

                            <span
                                x-text="zoomLabel()"
                                class="w-16 text-center text-xs font-semibold text-gray-700 tabular-nums dark:text-gray-200"
                            >100%</span>

                            <button
                                type="button"
                                x-on:click="zoomAt(1.3, $refs.drawing)"
                                aria-label="Zoom in"
                                class="rounded-md bg-white p-1.5 text-gray-700 shadow-xs ring-1 ring-gray-300 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-white/10"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>

                            <button
                                type="button"
                                x-on:click="fit()"
                                class="rounded-md bg-white px-2 py-1.5 text-xs font-semibold text-gray-700 shadow-xs ring-1 ring-gray-300 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-white/10"
                            >
                                Fit
                            </button>

                            <button
                                type="button"
                                x-on:click="$wire.download()"
                                title="Download the plot as a DXF drawing for AutoCAD, LibreCAD or QCAD"
                                class="rounded-md bg-white px-2 py-1.5 text-xs font-semibold text-gray-700 shadow-xs ring-1 ring-gray-300 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-white/10"
                            >
                                Download DXF
                            </button>

                            <button
                                type="button"
                                x-on:click="window.print()"
                                class="rounded-md bg-white px-2 py-1.5 text-xs font-semibold text-gray-700 shadow-xs ring-1 ring-gray-300 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-white/10"
                            >
                                Print
                            </button>
                        </div>
                    </div>

                    <div
                        x-ref="drawing"
                        x-on:wheel.prevent="wheel($event)"
                        x-on:pointerdown="startPan($event)"
                        x-on:pointermove.window="movePan($event)"
                        x-on:pointerup.window="endPan()"
                        x-on:pointercancel.window="endPan()"
                        class="group relative h-[440px] cursor-grab touch-none select-none overflow-hidden rounded-xl bg-gray-50 ring-1 ring-gray-200 active:cursor-grabbing sm:h-[520px] print:h-auto print:bg-white! print:ring-0 dark:bg-gray-950 dark:ring-gray-800 lg:h-[600px]"
                    >
                        <svg
                            viewBox="0 0 800 560"
                            class="block h-full w-full print:h-auto print:w-full"
                            role="img"
                            aria-label="Plot of lot {{ $lot_number }}, survey {{ $survey_number }}"
                        >
                            <g style="transform-box: view-box; transform-origin: 0 0; transform: translate(var(--plotter-pan-x, 0px), var(--plotter-pan-y, 0px)) scale(var(--plotter-zoom, 1));">
                                {{-- North is up: the bearings are absolute, so the frame says which way it points. --}}
                                <g transform="translate(44,44)" fill="none" stroke-width="1.5" class="stroke-black print:stroke-black! dark:stroke-white">
                                    <line x1="0" y1="16" x2="0" y2="-12" />
                                    <path d="M-5 -6 L0 -16 L5 -6" stroke-linecap="round" stroke-linejoin="round" />
                                </g>
                                <text x="44" y="72" text-anchor="middle" class="fill-black text-xs font-semibold print:fill-black! dark:fill-white">N</text>

                                <polygon points="{{ $plot['polygon'] }}" class="fill-brand-600 print:fill-gray-500!" fill-opacity="0.12" />

                                @foreach ($plot['segments'] as $segment)
                                    <line
                                        x1="{{ $segment['from']['x'] }}"
                                        y1="{{ $segment['from']['y'] }}"
                                        x2="{{ $segment['to']['x'] }}"
                                        y2="{{ $segment['to']['y'] }}"
                                        class="stroke-black print:stroke-black! dark:stroke-white"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                    />
                                @endforeach

                                @if ($plot['closure'])
                                    <line
                                        x1="{{ $plot['closure']['x1'] }}"
                                        y1="{{ $plot['closure']['y1'] }}"
                                        x2="{{ $plot['closure']['x2'] }}"
                                        y2="{{ $plot['closure']['y2'] }}"
                                        class="stroke-black print:stroke-black! dark:stroke-white"
                                        stroke-width="1.5"
                                        stroke-dasharray="6 6"
                                        stroke-opacity="0.45"
                                    />
                                @endif

                                @foreach ($plot['points'] as $point)
                                    <circle
                                        cx="{{ $point['x'] }}"
                                        cy="{{ $point['y'] }}"
                                        r="4"
                                        class="fill-black stroke-white print:fill-black! print:stroke-white! dark:fill-white dark:stroke-gray-950"
                                        stroke-width="2"
                                    />
                                @endforeach

                                {{-- The dimension of each side: hidden until the plot is hovered, always on paper. --}}
                                @foreach ($plot['segments'] as $segment)
                                    <g
                                        transform="translate({{ $segment['mid']['x'] }}, {{ $segment['mid']['y'] }}) rotate({{ $segment['angle'] }})"
                                        class="opacity-0 transition-opacity duration-150 group-hover:opacity-100 print:opacity-100! motion-reduce:transition-none pointer-coarse:opacity-100!"
                                    >
                                        <text
                                            x="0"
                                            y="-10"
                                            text-anchor="middle"
                                            class="fill-black stroke-white text-xs font-semibold print:fill-black! print:stroke-white! dark:fill-white dark:stroke-gray-950"
                                            stroke-width="3"
                                            paint-order="stroke"
                                        >{{ $segment['label'] }}</text>
                                    </g>
                                @endforeach
                            </g>
                        </svg>
                    </div>

                    <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <div>
                            <dt class="inline font-medium text-gray-500 dark:text-gray-400">Tie lines </dt>
                            <dd class="inline text-gray-900 dark:text-white">{{ $plot['lineCount'] }}</dd>
                        </div>

                        <div>
                            <dt class="inline font-medium text-gray-500 dark:text-gray-400">Unit </dt>
                            <dd class="inline text-gray-900 dark:text-white">{{ $plot['unit'] }}</dd>
                        </div>

                        @if ($plot['closure'])
                            <div>
                                <dt class="inline font-medium text-gray-500 dark:text-gray-400">Does not close </dt>
                                <dd class="inline text-gray-900 dark:text-white">
                                    {{ $plot['closure']['label'] }} apart at the last corner
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if ($plot['declaredCount'] > $plot['lineCount'])
                        <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">
                            {{ $plot['declaredCount'] - $plot['lineCount'] }} of the {{ $plot['declaredCount'] }} lines written
                            could not be read, so they are not on the plot.
                        </p>
                    @endif
                @else
                    <div class="flex h-[440px] flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 px-6 text-center dark:border-gray-700 sm:h-[520px] lg:h-[600px]">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Nothing to plot yet.</p>
                        <p class="max-w-sm text-sm text-gray-500 dark:text-gray-400">
                            Each tie line in the description becomes one side of the parcel, drawn to scale with its
                            distance on it.
                        </p>
                    </div>
                @endif
            </div>
        </x-panel>
    </div>

    <div class="mt-6 print:hidden">
        <x-panel
            title="Saved plotters"
            :description="$this->isFiltered ? $this->plotters->total().' match your search.' : 'Open one to plot it again.'"
            :padded="false"
        >
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                <div class="relative sm:w-80">
                    <label for="plotter-search" class="sr-only">Search saved plotters</label>

                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-300">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </span>

                    <input
                        id="plotter-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search title, name, lot, survey or location"
                        autocomplete="off"
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pr-3 pl-9 text-sm text-gray-900 shadow-xs placeholder-gray-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-hidden [&::-webkit-search-cancel-button]:appearance-none dark:border-gray-700 dark:bg-white/5 dark:text-white dark:placeholder-gray-300"
                    />
                </div>

                @if ($this->isFiltered)
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="shrink-0 self-start text-sm font-medium text-brand-600 hover:underline sm:self-auto dark:text-brand-300"
                    >
                        Clear search
                    </button>
                @endif
            </div>

            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Title number</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Name on title</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Lot</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Survey</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Area</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Location</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-300">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($this->plotters as $plotter)
                        <tr wire:key="plotter-{{ $plotter->id }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $this->highlight($plotter->title_number) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $plotter->title_name !== null && $plotter->title_name !== '' ? $this->highlight($plotter->title_name) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $this->highlight($plotter->lot_number) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $this->highlight($plotter->survey_number) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $plotter->area ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $plotter->location !== null && $plotter->location !== '' ? $this->highlight($plotter->location) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    x-on:click="$wire.startEdit({{ $plotter->id }})"
                                    class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                                >
                                    Open
                                </button>

                                <button
                                    type="button"
                                    data-label="{{ $plotter->title_number }}"
                                    x-on:click="$dispatch('modal-open', { id: 'plotter-delete', payload: { id: {{ $plotter->id }}, label: $el.dataset.label } })"
                                    class="ml-3 font-medium text-red-600 hover:underline dark:text-red-400"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-gray-500 dark:text-gray-300">
                                @if ($this->isFiltered)
                                    No plotters match your search.
                                    <button
                                        type="button"
                                        wire:click="clearFilters"
                                        class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                                    >
                                        Clear search
                                    </button>
                                @else
                                    No plotters saved yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($this->plotters->hasPages())
                <div class="px-4 pt-4 pb-4">
                    {{ $this->plotters->links() }}
                </div>
            @endif
        </x-panel>
    </div>

    <x-confirm-modal
        id="plotter-delete"
        title="Delete plotter?"
        message="&#8212; this cannot be undone."
        action="delete"
        error-key="plotters"
        confirm-label="Delete plotter"
    />
</div>