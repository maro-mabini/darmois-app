<?php

use App\Models\File;
use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Models\Station;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * The bar chart is drawn in pixels so the count printed above a bar cannot
     * push it out of its track. This is the height of the busiest month.
     */
    private const CHART_HEIGHT = 128;

    /**
     * Headline numbers, counted once per request.
     *
     * @return array{records: int, files: int, locations: int, stations: int, recordTypes: int, ownerTypes: int, addedThisMonth: int, lastRecordAt: Carbon|null}
     */
    #[Computed]
    public function totals(): array
    {
        $lastRecord = RecordsOwner::query()->max('created_at');

        return [
            'records' => RecordsOwner::query()->count(),
            'files' => File::query()->count(),
            'locations' => Location::query()->count(),
            'stations' => Station::query()->count(),
            'recordTypes' => RecordType::query()->count(),
            'ownerTypes' => OwnerType::query()->count(),
            'addedThisMonth' => RecordsOwner::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'lastRecordAt' => is_string($lastRecord) ? Carbon::parse($lastRecord) : null,
        ];
    }

    /**
     * The most recently added records, with everything the rows print.
     *
     * id is the tie-break: two records saved in the same second would
     * otherwise swap places between requests.
     *
     * @return Collection<int, RecordsOwner>
     */
    #[Computed]
    public function recentRecords(): Collection
    {
        return RecordsOwner::query()
            ->with(['ownerType', 'recordType', 'location', 'station'])
            ->withCount('files')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /**
     * Every record type, busiest first, scaled against the busiest one.
     *
     * @return array<int, array{name: string, count: int, share: float}>
     */
    #[Computed]
    public function recordTypeBreakdown(): array
    {
        $types = RecordType::query()
            ->withCount('records')
            ->orderByDesc('records_count')
            ->orderBy('name')
            ->get();

        $busiest = max(1, (int) $types->max('records_count'));

        return $types->map(fn (RecordType $type): array => [
            'name' => $type->name,
            'count' => (int) $type->records_count,
            'share' => round(((int) $type->records_count / $busiest) * 100, 1),
        ])->all();
    }

    /**
     * Records added in each of the last six months, oldest first.
     *
     * The dates are grouped in PHP rather than with a date function in SQL so
     * the dashboard behaves the same on SQLite and Postgres.
     *
     * @return array<int, array{label: string, count: int, height: int, current: bool}>
     */
    #[Computed]
    public function activity(): array
    {
        $start = now()->startOfMonth()->subMonths(5);

        $counts = RecordsOwner::query()
            ->where('created_at', '>=', $start)
            ->pluck('created_at')
            ->countBy(fn (CarbonInterface $added): string => $added->format('Y-m'));

        $months = collect(range(0, 5))->map(function (int $offset) use ($start, $counts): array {
            $month = $start->copy()->addMonths($offset);
            $key = $month->format('Y-m');

            return [
                'label' => $month->format('M'),
                'count' => (int) $counts->get($key, 0),
                'current' => $key === now()->format('Y-m'),
            ];
        });

        $busiest = max(1, (int) $months->max('count'));

        return $months->map(fn (array $month): array => [
            'label' => $month['label'],
            'count' => $month['count'],
            'height' => $month['count'] > 0
                ? max(10, (int) round(($month['count'] / $busiest) * self::CHART_HEIGHT))
                : 3,
            'current' => $month['current'],
        ])->all();
    }

    /**
     * The locations holding the most records.
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function busiestLocations(): Collection
    {
        return Location::query()
            ->withCount('records')
            ->orderByDesc('records_count')
            ->orderBy('name')
            ->limit(4)
            ->get();
    }

    /**
     * The stations holding the most records.
     *
     * @return Collection<int, Station>
     */
    #[Computed]
    public function busiestStations(): Collection
    {
        return Station::query()
            ->withCount('records')
            ->orderByDesc('records_count')
            ->orderBy('name')
            ->limit(4)
            ->get();
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Dashboard
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                Records, files and filing points across the DARMOIS workspace.
            </p>
        </div>

        <div class="mt-4 flex gap-3 sm:mt-0">
            <a
                href="{{ route('admin.record-types.index') }}"
                wire:navigate
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
            >
                Manage types
            </a>

            <a
                href="{{ route('admin.records.index') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
            >
                View records
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </header>

    {{-- The one block of colour on the page: the headline number and the three
         counts that explain what makes it up. --}}
    <section class="relative mt-8 overflow-hidden rounded-2xl bg-linear-to-br from-brand-800 via-brand-700 to-brand-600 px-6 py-8 shadow-lg sm:px-8">
        <div class="pointer-events-none absolute -top-24 -right-16 size-64 rounded-full bg-gold-400/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-10 size-72 rounded-full bg-brand-400/30 blur-3xl" aria-hidden="true"></div>

        <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-center">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-brand-200 uppercase">
                    Records at a glance
                </p>

                <p class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1 text-white">
                    <span class="text-5xl font-semibold tracking-tight tabular-nums">
                        {{ number_format($this->totals['records']) }}
                    </span>
                    <span class="text-lg text-brand-100">
                        {{ Str::plural('record', $this->totals['records']) }} on file
                    </span>
                </p>

                <p class="mt-3 max-w-prose text-sm text-brand-100">
                    {{ number_format($this->totals['files']) }} {{ Str::plural('file', $this->totals['files']) }}
                    held across {{ number_format($this->totals['locations']) }} {{ Str::plural('location', $this->totals['locations']) }}
                    and {{ number_format($this->totals['stations']) }} {{ Str::plural('station', $this->totals['stations']) }}.
                    @if ($this->totals['lastRecordAt'] !== null)
                        The newest record was added {{ $this->totals['lastRecordAt']->format('j M Y') }}.
                    @endif
                </p>

                <p class="mt-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white ring-1 ring-white/15">
                    <svg class="size-3.5 text-gold-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>

                    @if ($this->totals['addedThisMonth'] > 0)
                        {{ $this->totals['addedThisMonth'] }} added this month
                    @else
                        Nothing added this month yet
                    @endif
                </p>
            </div>

            <dl class="grid grid-cols-3 gap-3 sm:gap-4">
                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur-sm">
                    <dt class="min-h-8 text-xs font-medium text-brand-100 sm:min-h-0">Files</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums text-white">
                        {{ number_format($this->totals['files']) }}
                    </dd>
                </div>

                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur-sm">
                    <dt class="min-h-8 text-xs font-medium text-brand-100 sm:min-h-0">Record types</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums text-white">
                        {{ number_format($this->totals['recordTypes']) }}
                    </dd>
                </div>

                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur-sm">
                    <dt class="min-h-8 text-xs font-medium text-brand-100 sm:min-h-0">Owner types</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums text-white">
                        {{ number_format($this->totals['ownerTypes']) }}
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
        <x-panel
            title="Recent records"
            description="The five most recently added."
            class="overflow-hidden lg:col-span-2"
            :padded="false"
        >
            <x-slot:actions>
                <a
                    href="{{ route('admin.records.index') }}"
                    wire:navigate
                    class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                >
                    View all
                </a>
            </x-slot:actions>

            @if ($this->recentRecords->isEmpty())
                <div class="p-6">
                    <div class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">No records yet</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                            Add the first one from the records page.
                        </p>
                        <a
                            href="{{ route('admin.records.index') }}"
                            wire:navigate
                            class="mt-4 inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-brand-500"
                        >
                            Go to records
                        </a>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead>
                            <tr class="text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                <th scope="col" class="px-4 py-3 font-medium sm:px-6">Owner</th>
                                <th scope="col" class="hidden px-4 py-3 font-medium sm:table-cell sm:px-6">Record type</th>
                                <th scope="col" class="hidden px-4 py-3 font-medium md:table-cell sm:px-6">Filed at</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium sm:px-6">Added</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->recentRecords as $record)
                                <tr
                                    wire:key="recent-record-{{ $record->id }}"
                                    x-data
                                    x-on:click="if (! $event.target.closest('a, button')) Livewire.navigate(@js(route('admin.records.show', $record)))"
                                    class="cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                                >
                                    <td class="px-4 py-3 sm:px-6">
                                        <a
                                            href="{{ route('admin.records.show', $record) }}"
                                            wire:navigate
                                            class="font-medium text-gray-900 hover:text-brand-600 hover:underline dark:text-white dark:hover:text-brand-300"
                                        >
                                            {{ $record->owner_name }}
                                        </a>
                                        <p class="text-xs text-gray-500 dark:text-gray-300">
                                            {{ $record->ownerType->name }}
                                            {{-- The record type has its own column from sm
                                                 up, so on a phone it moves under the owner. --}}
                                            <span class="sm:hidden">· {{ $record->recordType->name }}</span>
                                        </p>
                                    </td>

                                    <td class="hidden px-4 py-3 sm:table-cell sm:px-6">
                                        <span class="inline-flex rounded-md bg-brand-50 px-2 py-1 text-xs font-medium whitespace-nowrap text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                            {{ $record->recordType->name }}
                                        </span>
                                    </td>

                                    <td class="hidden px-4 py-3 md:table-cell sm:px-6">
                                        <span class="block truncate text-gray-700 dark:text-gray-200">
                                            {{ $record->location?->name ?? '—' }}
                                        </span>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-300">
                                            {{ $record->station?->name ?? '—' }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right whitespace-nowrap sm:px-6">
                                        <span class="block text-gray-600 dark:text-gray-300">
                                            {{ $record->created_at?->format('j M Y') }}
                                        </span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-300">
                                            {{ $record->files_count }} {{ Str::plural('file', $record->files_count) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel>

        <x-panel title="By record type" description="How the collection is categorised.">
            @if ($this->recordTypeBreakdown === [])
                <p class="text-sm text-gray-500 dark:text-gray-300">No record types yet.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($this->recordTypeBreakdown as $type)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate font-medium text-gray-900 dark:text-white">
                                    {{ $type['name'] }}
                                </span>
                                <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-300">
                                    {{ $type['count'] }}
                                </span>
                            </div>

                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div
                                    class="h-full rounded-full bg-brand-500"
                                    style="width: {{ $type['share'] }}%"
                                ></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <a
                href="{{ route('admin.record-types.index') }}"
                wire:navigate
                class="mt-6 inline-flex text-sm font-medium text-brand-600 hover:underline dark:text-brand-300"
            >
                Manage record types
            </a>
        </x-panel>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-panel title="Added over time" description="Records added in the last six months." class="lg:col-span-2">
            <x-slot:actions>
                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium tabular-nums text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ array_sum(array_column($this->activity, 'count')) }} in six months
                </span>
            </x-slot:actions>

            <div
                role="img"
                aria-label="Records added per month: {{ collect($this->activity)->map(fn (array $month): string => $month['label'].' '.$month['count'])->join(', ') }}"
                class="flex h-48 items-end gap-2 sm:gap-4"
            >
                @foreach ($this->activity as $month)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1.5" aria-hidden="true">
                        <span @class([
                            'text-xs font-medium tabular-nums',
                            'text-gray-700 dark:text-gray-200' => $month['count'] > 0,
                            'text-transparent' => $month['count'] === 0,
                        ])>
                            {{ $month['count'] }}
                        </span>

                        <div @class([
                            'w-full max-w-14 rounded-t-lg',
                            'bg-brand-500' => $month['count'] > 0 && ! $month['current'],
                            'bg-gold-400' => $month['count'] > 0 && $month['current'],
                            'bg-gold-400/25' => $month['count'] === 0 && $month['current'],
                            'bg-gray-100 dark:bg-gray-800' => $month['count'] === 0 && ! $month['current'],
                        ]) style="height: {{ $month['height'] }}px"></div>

                        <span @class([
                            'text-xs',
                            'font-semibold text-gray-900 dark:text-white' => $month['current'],
                            'text-gray-500 dark:text-gray-300' => ! $month['current'],
                        ])>
                            {{ $month['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-300">
                The highlighted bar is the current month.
            </p>
        </x-panel>

        <x-panel title="Filing points" description="Where the records are kept.">
            <div class="space-y-6">
                <div>
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-300">
                            Locations
                        </h3>
                        <a
                            href="{{ route('admin.locations.index') }}"
                            wire:navigate
                            class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300"
                        >
                            All {{ $this->totals['locations'] }}
                        </a>
                    </div>

                    <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->busiestLocations as $location)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="truncate text-gray-700 dark:text-gray-200">
                                    {{ $location->name }}
                                </span>
                                <span class="shrink-0 rounded-md bg-gray-100 px-2 py-0.5 text-xs tabular-nums text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $location->records_count }}
                                </span>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500 dark:text-gray-300">No locations yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div>
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-300">
                            Stations
                        </h3>
                        <a
                            href="{{ route('admin.stations.index') }}"
                            wire:navigate
                            class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300"
                        >
                            All {{ $this->totals['stations'] }}
                        </a>
                    </div>

                    <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->busiestStations as $station)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="truncate text-gray-700 dark:text-gray-200">
                                    {{ $station->name }}
                                </span>
                                <span class="shrink-0 rounded-md bg-gray-100 px-2 py-0.5 text-xs tabular-nums text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $station->records_count }}
                                </span>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500 dark:text-gray-300">No stations yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </x-panel>
    </div>
</div>
