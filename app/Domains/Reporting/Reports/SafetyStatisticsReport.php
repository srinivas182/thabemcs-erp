<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Safety\Models\ToolboxTalk;
use App\Domains\Site\Models\Inspection;

final class SafetyStatisticsReport implements Report
{
    public function key(): string
    {
        return 'safety-statistics';
    }

    public function title(): string
    {
        return 'Safety statistics';
    }

    public function description(): string
    {
        return 'Incidents by type, reportable incidents, toolbox talks and safety inspection results per project.';
    }

    public function gate(): string
    {
        return 'view-safety-reports';
    }

    public function filters(): array
    {
        return ['project', 'period'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $range = [$filters->from, $filters->to];
        $rows = Project::query()->when($filters->project, fn ($q) => $q->whereKey($filters->project?->id))->orderBy('code')->get(['id', 'code', 'name'])
            ->map(static function (Project $p) use ($range): array {
                $incidents = SafetyIncident::query()->where('project_id', $p->id)->whereBetween('occurred_at', $range)->get(['type', 'reportable']);
                $count = static fn (array $types): int => $incidents->filter(static fn (SafetyIncident $i): bool => in_array($i->type->value, $types, true))->count();
                $inspections = Inspection::query()->where('project_id', $p->id)->where('kind', 'safety')->whereBetween('inspected_on', [$range[0]->toDateString(), $range[1]->toDateString()])->pluck('result');

                return [
                    'project' => "{$p->code} {$p->name}", 'near_miss' => $count(['near_miss']), 'first_aid' => $count(['first_aid']),
                    'medical' => $count(['medical']), 'lost_time' => $count(['lost_time']), 'fatality' => $count(['fatality']),
                    'reportable' => $incidents->where('reportable', true)->count(),
                    'talks' => ToolboxTalk::query()->where('project_id', $p->id)->whereBetween('held_on', [$range[0]->toDateString(), $range[1]->toDateString()])->count(),
                    'inspections' => $inspections->count(),
                    'failed' => $inspections->count() ? round($inspections->filter(static fn ($r): bool => $r !== 'pass')->count() / $inspections->count() * 100, 1) : null,
                ];
            })->values()->all();

        return new ReportResult($this->title(), $filters->describe(true, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'near_miss', 'label' => 'Near misses', 'type' => 'number'],
            ['key' => 'first_aid', 'label' => 'First aid', 'type' => 'number'], ['key' => 'medical', 'label' => 'Medical', 'type' => 'number'],
            ['key' => 'lost_time', 'label' => 'Lost time', 'type' => 'number'], ['key' => 'fatality', 'label' => 'Fatalities', 'type' => 'number'],
            ['key' => 'reportable', 'label' => 'Reportable', 'type' => 'number'], ['key' => 'talks', 'label' => 'Toolbox talks', 'type' => 'number'],
            ['key' => 'inspections', 'label' => 'Safety inspections', 'type' => 'number'], ['key' => 'failed', 'label' => 'Not passed', 'type' => 'percent'],
        ], $rows, ['project' => 'Total', ...ReportResult::sum($rows, ['near_miss', 'first_aid', 'medical', 'lost_time', 'fatality', 'reportable', 'talks', 'inspections'])]);
    }
}
