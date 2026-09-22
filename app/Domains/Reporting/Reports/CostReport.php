<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

final class CostReport implements Report
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function key(): string
    {
        return 'cost-report';
    }

    public function title(): string
    {
        return 'Cost report';
    }

    public function description(): string
    {
        return 'Budget, variations, commitments and what is left, by cost code (excl. VAT).';
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
        $rows = BudgetLine::query()->with('project:id,code,name')
            ->when($filters->project, fn ($q) => $q->where('project_id', $filters->project?->id))
            ->orderBy('project_id')->orderBy('code')->get()
            ->map(function (BudgetLine $line): array {
                $f = $this->budgets->figures($line);

                return [
                    'project' => $line->project->code, 'code' => $f['code'], 'description' => $f['description'], 'original' => $f['original'],
                    'variations' => $f['variations'], 'revised' => $f['revised'], 'committed' => $f['committed'], 'direct' => $f['direct'],
                    'available' => $f['available'], 'used' => $f['used'],
                ];
            })->values()->all();

        $totals = ['project' => 'Total', ...ReportResult::sum($rows, ['original', 'variations', 'revised', 'committed', 'direct', 'available'])];

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'code', 'label' => 'Code', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Cost code', 'type' => 'text'], ['key' => 'original', 'label' => 'Original budget', 'type' => 'money'],
            ['key' => 'variations', 'label' => 'Variations', 'type' => 'money'], ['key' => 'revised', 'label' => 'Revised budget', 'type' => 'money'],
            ['key' => 'committed', 'label' => 'Committed (POs)', 'type' => 'money'], ['key' => 'direct', 'label' => 'Direct invoices', 'type' => 'money'],
            ['key' => 'available', 'label' => 'Available', 'type' => 'money'], ['key' => 'used', 'label' => 'Used', 'type' => 'percent'],
        ], $rows, $totals);
    }
}
