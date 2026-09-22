<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

/**
 * Creditors age analysis: what is owed to each supplier by days past the due date.
 */
final class SupplierAgeAnalysisReport implements Report
{
    public function key(): string
    {
        return 'supplier-age-analysis';
    }

    public function title(): string
    {
        return 'Supplier age analysis';
    }

    public function description(): string
    {
        return 'Unpaid supplier invoices by days overdue (incl. VAT): not yet due, 1–30, 31–60, 61–90 and over 90 days.';
    }

    public function gate(): string
    {
        return 'view-financial-reports';
    }

    public function filters(): array
    {
        return ['project', 'as_at'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $bySupplier = [];
        SupplierInvoice::query()->with('supplier:id,name')
            ->whereNotIn('status', ['paid', 'rejected'])
            ->whereDate('invoice_date', '<=', $filters->asAt->toDateString())
            ->when($filters->project, fn ($q) => $q->where('project_id', $filters->project?->id))
            ->get()
            ->each(function (SupplierInvoice $i) use (&$bySupplier, $filters): void {
                $days = (int) $i->due_date->startOfDay()->diffInDays($filters->asAt->copy()->startOfDay(), false);
                $bucket = match (true) {
                    $days <= 0 => 'current', $days <= 30 => 'd30', $days <= 60 => 'd60', $days <= 90 => 'd90', default => 'over90',
                };
                $name = $i->supplier->name;
                $bySupplier[$name] ??= ['supplier' => $name, 'current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'over90' => 0.0, 'total' => 0.0];
                $bySupplier[$name][$bucket] += (float) $i->total;
                $bySupplier[$name]['total'] += (float) $i->total;
            });

        ksort($bySupplier);
        $rows = array_values(array_map(static fn (array $r): array => array_map(static fn ($v) => is_float($v) ? round($v, 2) : $v, $r), $bySupplier));

        return new ReportResult($this->title(), $filters->describe(false, true), [
            ['key' => 'supplier', 'label' => 'Supplier', 'type' => 'text'], ['key' => 'current', 'label' => 'Not yet due', 'type' => 'money'],
            ['key' => 'd30', 'label' => '1–30 days', 'type' => 'money'], ['key' => 'd60', 'label' => '31–60 days', 'type' => 'money'],
            ['key' => 'd90', 'label' => '61–90 days', 'type' => 'money'], ['key' => 'over90', 'label' => 'Over 90 days', 'type' => 'money'],
            ['key' => 'total', 'label' => 'Total owed', 'type' => 'money'],
        ], $rows, ['supplier' => 'Total', ...ReportResult::sum($rows, ['current', 'd30', 'd60', 'd90', 'over90', 'total'])]);
    }
}
