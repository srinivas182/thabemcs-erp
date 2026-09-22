<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Programme\Services\ScheduleService;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

/**
 * Activities that are critical or behind, across projects: the weekly programme review.
 */
final class ProgrammeStatusReport implements Report
{
    public function __construct(private readonly ScheduleService $schedule) {}

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
        $rows = [];
        $projects = Project::query()->when($filters->project, fn ($q) => $q->whereKey($filters->project?->id))->where('status', 'active')->orderBy('code')->get();

        foreach ($projects as $project) {
            $plan = $this->schedule->calculate($project);
            foreach (ProgrammeActivity::query()->where('project_id', $project->id)->orderBy('sort')->get() as $a) {
                $d = $plan['activities'][$a->id] ?? null;
                if ($d === null || $a->percent_complete >= 100 || (! $d['critical'] && ! $d['behind'])) {
                    continue;
                }
                $rows[] = [
                    'project' => $project->code, 'activity' => trim(($a->wbs ?? '').' '.$a->name), 'start' => $d['earlyStart'], 'finish' => $d['earlyFinish'],
                    'float' => $d['float'], 'percent' => $a->percent_complete, 'flag' => $d['behind'] ? 'Behind' : 'Critical',
                ];
            }
        }

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'activity', 'label' => 'Activity', 'type' => 'text'],
            ['key' => 'start', 'label' => 'Start', 'type' => 'date'], ['key' => 'finish', 'label' => 'Forecast finish', 'type' => 'date'],
            ['key' => 'float', 'label' => 'Float (days)', 'type' => 'number'], ['key' => 'percent', 'label' => 'Complete', 'type' => 'percent'], ['key' => 'flag', 'label' => 'Status', 'type' => 'text'],
        ], $rows, null, 'Shows unfinished activities that are critical (no float) or past their forecast finish.');
    }
}
