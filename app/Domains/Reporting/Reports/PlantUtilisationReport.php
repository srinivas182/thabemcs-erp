<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Plant\Models\PlantEvent;
use App\Domains\Plant\Models\PlantItem;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;
use Illuminate\Support\Carbon;

/**
 * Days each item spent on site in the period (from its movement history), utilisation and hire cost.
 */
final class PlantUtilisationReport implements Report
{
    public function key(): string
    {
        return 'plant-utilisation';
    }

    public function title(): string
    {
        return 'Plant utilisation';
    }

    public function description(): string
    {
        return 'Days on site, days broken down, utilisation and hire cost per item in the period.';
    }

    public function gate(): string
    {
        return 'manage-plant';
    }

    public function filters(): array
    {
        return ['period'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $periodDays = (int) $filters->from->copy()->startOfDay()->diffInDays($filters->to->copy()->startOfDay()) + 1;

        $rows = PlantItem::query()->with(['supplier:id,name', 'project:id,code'])->orderBy('asset_number')->get()
            ->map(function (PlantItem $p) use ($filters, $periodDays): array {
                $events = PlantEvent::query()->where('plant_item_id', $p->id)->orderBy('happened_on')->orderBy('id')->get();
                [$onSite, $broken] = $this->days($events->all(), $filters->from, $filters->to);
                $rate = $p->hire_rate_per_day === null ? null : (float) $p->hire_rate_per_day;

                return [
                    'asset' => $p->asset_number, 'description' => $p->description, 'ownership' => $p->ownership === 'hired' ? 'Hired: '.($p->supplier->name ?? '') : 'Owned',
                    'location' => $p->project->code ?? 'Yard', 'on_site' => $onSite, 'broken' => $broken,
                    'utilisation' => $periodDays ? round(max(0, $onSite - $broken) / $periodDays * 100, 1) : 0.0,
                    'hire_cost' => $p->ownership === 'hired' && $rate !== null ? round($onSite * $rate, 2) : null,
                ];
            })->values()->all();

        return new ReportResult($this->title(), $filters->describe(true, false), [
            ['key' => 'asset', 'label' => 'Asset', 'type' => 'text'], ['key' => 'description', 'label' => 'Description', 'type' => 'text'],
            ['key' => 'ownership', 'label' => 'Owned or hired', 'type' => 'text'], ['key' => 'location', 'label' => 'Now at', 'type' => 'text'],
            ['key' => 'on_site', 'label' => 'Days on site', 'type' => 'number'], ['key' => 'broken', 'label' => 'Days broken down', 'type' => 'number'],
            ['key' => 'utilisation', 'label' => 'Utilisation', 'type' => 'percent'], ['key' => 'hire_cost', 'label' => 'Hire cost', 'type' => 'money'],
        ], $rows, ['asset' => 'Total', ...ReportResult::sum($rows, ['on_site', 'broken', 'hire_cost'])],
            'Utilisation is days on site and working, as a share of the days in the period.');
    }

    /**
     * Walk the event history: "moved" puts the item on site, "off_hired" takes it off;
     * "breakdown" to "repaired" counts as broken.
     *
     * @param  array<int, PlantEvent>  $events
     * @return array{0: int, 1: int}
     */
    private function days(array $events, Carbon $from, Carbon $to): array
    {
        $onSite = 0;
        $broken = 0;
        $siteStart = null;
        $breakStart = null;
        $count = static function (?Carbon $start, Carbon $end) use ($from, $to): int {
            if ($start === null) {
                return 0;
            }
            $a = $start->greaterThan($from) ? $start->copy() : $from->copy();
            $b = $end->lessThan($to) ? $end->copy() : $to->copy();

            return $b->lessThan($a) ? 0 : (int) $a->startOfDay()->diffInDays($b->startOfDay()) + 1;
        };

        foreach ($events as $e) {
            $day = $e->happened_on->copy();
            match ($e->type) {
                'moved' => $siteStart ??= $day,
                'off_hired' => [$onSite += $count($siteStart, $day->copy()->subDay()), $siteStart = null],
                'breakdown' => $breakStart ??= $day,
                'repaired' => [$broken += $count($breakStart, $day->copy()->subDay()), $breakStart = null],
                default => null,
            };
        }

        return [$onSite + $count($siteStart, $to), $broken + $count($breakStart, $to)];
    }
}
