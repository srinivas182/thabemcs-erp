<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Contracts\Models\Contract;
use App\Domains\Contracts\Models\PaymentCertificate;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

final class RetentionScheduleReport implements Report
{
    public function key(): string
    {
        return 'retention-schedule';
    }

    public function title(): string
    {
        return 'Retention schedule';
    }

    public function description(): string
    {
        return 'Retention held and released per contract from the latest certified payment certificate, with completion dates.';
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
        $rows = Contract::query()->with(['supplier:id,name', 'project:id,code'])
            ->when($filters->project, fn ($q) => $q->where('project_id', $filters->project?->id))
            ->orderBy('project_id')->get()
            ->map(static function (Contract $c): array {
                $last = PaymentCertificate::query()->where('contract_id', $c->id)->where('status', 'certified')->orderByDesc('number')->first();
                $held = $last ? (float) $last->retention_held : 0.0;
                $released = $last ? (float) $last->retention_released : 0.0;

                return [
                    'project' => $c->project->code, 'contract' => $c->reference, 'contractor' => $c->supplier->name, 'sum' => (float) $c->contract_sum,
                    'certified' => $last ? (float) $last->gross_value : 0.0, 'held' => $held, 'released' => $released, 'net' => round($held - $released, 2),
                    'practical' => $c->practical_completion_on?->toDateString(), 'final' => $c->final_completion_on?->toDateString(),
                ];
            })->values()->all();

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'contract', 'label' => 'Contract', 'type' => 'text'],
            ['key' => 'contractor', 'label' => 'Contractor', 'type' => 'text'], ['key' => 'sum', 'label' => 'Contract sum', 'type' => 'money'],
            ['key' => 'certified', 'label' => 'Certified to date', 'type' => 'money'], ['key' => 'held', 'label' => 'Retention held', 'type' => 'money'],
            ['key' => 'released', 'label' => 'Released', 'type' => 'money'], ['key' => 'net', 'label' => 'Still held', 'type' => 'money'],
            ['key' => 'practical', 'label' => 'Practical completion', 'type' => 'date'], ['key' => 'final', 'label' => 'Final completion', 'type' => 'date'],
        ], $rows, ['project' => 'Total', ...ReportResult::sum($rows, ['sum', 'certified', 'held', 'released', 'net'])]);
    }
}
