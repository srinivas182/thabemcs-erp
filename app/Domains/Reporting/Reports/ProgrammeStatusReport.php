<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

/**
 * Activities that are critical or behind, across projects: the weekly programme review.
 */
final class ProgrammeStatusReport implements Report
{
    public function key(): string
    {
        return 'programme-status';
    }

    public function title(): string
    {
        return 'Programme status';
    }

    public function description(): string
    {
        return 'Activities on the critical path or behind programme, with forecast finish and float.';
    }

    public function gate(): string
    {
        return 'view-financial-reports';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $today = now('Africa/Johannesburg')->toDateString();
        $limit = (int) config('reporting.row_limit', 5000);
        // Uses the critical path stored on each activity by the metrics refresh; no recalculation here.
        $query = ProgrammeActivity::query()->with('project:id,code')
            ->whereHas('project', fn ($q) => $q->where('status', 'active'))
            ->when($filters->project, fn ($q) => $q->where('project_id', $filters->project?->id))
            ->where('percent_complete', '<', 100)
            ->where(fn ($q) => $q->where('is_critical', true)->orWhere('early_finish', '<', $today))
            ->orderBy('project_id')->orderBy('early_finish');
        $total = (clone $query)->count();

        $rows = $query->limit($limit)->get()->map(static fn (ProgrammeActivity $a): array => [
            'project' => $a->project->code, 'activity' => trim(($a->wbs ?? '').' '.$a->name), 'start' => $a->early_start?->toDateString(),
            'finish' => $a->early_finish?->toDateString(), 'float' => $a->total_float, 'percent' => $a->percent_complete,
            'flag' => $a->early_finish !== null && $a->early_finish->toDateString() < $today ? 'Behind' : 'Critical',
        ])->values()->all();

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'activity', 'label' => 'Activity', 'type' => 'text'],
            ['key' => 'start', 'label' => 'Start', 'type' => 'date'], ['key' => 'finish', 'label' => 'Forecast finish', 'type' => 'date'],
            ['key' => 'float', 'label' => 'Float (days)', 'type' => 'number'], ['key' => 'percent', 'label' => 'Complete', 'type' => 'percent'], ['key' => 'flag', 'label' => 'Status', 'type' => 'text'],
        ], $rows, null, 'Unfinished activities that are critical (no float) or past their forecast finish.'.($total > $limit ? " Showing the first {$limit} of {$total}; filter by project to see the rest." : ''));
    }
}
