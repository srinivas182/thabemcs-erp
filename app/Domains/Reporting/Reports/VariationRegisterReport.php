<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Finance\Models\VariationOrder;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

final class VariationRegisterReport implements Report
{
    public function key(): string
    {
        return 'variation-register';
    }

    public function title(): string
    {
        return 'Variation register';
    }

    public function description(): string
    {
        return 'Every variation order with its reason, cost code, amount, time impact and approval status.';
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
        $rows = VariationOrder::query()->with(['project:id,code', 'budgetLine:id,code'])
            ->when($filters->project, fn ($q) => $q->where('project_id', $filters->project?->id))
            ->orderBy('project_id')->orderBy('number')->get()
            ->map(static fn (VariationOrder $v): array => [
                'project' => $v->project->code, 'reference' => $v->reference(), 'title' => $v->title, 'reason' => str_replace('_', ' ', $v->reason),
                'code' => $v->budgetLine->code, 'amount' => (float) $v->amount, 'days' => $v->time_impact_days, 'status' => str_replace('_', ' ', $v->status),
                'approved' => $v->approved_at?->toDateString(),
            ])->values()->all();

        $approved = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'approved'));

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'reference', 'label' => 'VO', 'type' => 'text'], ['key' => 'title', 'label' => 'Variation', 'type' => 'text'],
            ['key' => 'reason', 'label' => 'Reason', 'type' => 'text'], ['key' => 'code', 'label' => 'Cost code', 'type' => 'text'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ['key' => 'days', 'label' => 'Days', 'type' => 'number'], ['key' => 'status', 'label' => 'Status', 'type' => 'text'], ['key' => 'approved', 'label' => 'Approved', 'type' => 'date'],
        ], $rows, ['project' => 'Approved total', ...ReportResult::sum($approved, ['amount', 'days'])]);
    }
}
