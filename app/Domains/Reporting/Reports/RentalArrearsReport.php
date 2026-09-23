<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Services\LeaseService;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

/**
 * Tenant arrears by age, for the weekly collections meeting.
 */
final class RentalArrearsReport implements Report
{
    public function __construct(private readonly LeaseService $leases) {}

    public function key(): string
    {
        return 'rental-arrears';
    }

    public function title(): string
    {
        return 'Tenant arrears';
    }

    public function description(): string
    {
        return 'What each tenant owes, split by how overdue it is.';
    }

    public function gate(): string
    {
        return 'view-rentals';
    }

    public function filters(): array
    {
        return ['as_at'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $asAt = $filters->asAt;
        $rows = [];

        foreach (Lease::query()->with(['unit.project:id,code', 'tenant:id,name', 'invoices'])->whereIn('status', ['active', 'ended'])->orderBy('number')->get() as $lease) {
            $owing = $this->leases->arrears($lease, $asAt);
            if ($owing['total'] <= 0) {
                continue;
            }
            $rows[] = [
                'project' => $lease->unit->project->code, 'unit' => $lease->unit->reference, 'tenant' => $lease->tenant->name,
                'current' => $owing['current'], 'days30' => $owing['days30'], 'days60' => $owing['days60'], 'days90' => $owing['days90'],
                'total' => $owing['total'], 'oldest' => $owing['oldestDue'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return new ReportResult($this->title(), $filters->describe(false, true), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
            ['key' => 'tenant', 'label' => 'Tenant', 'type' => 'text'], ['key' => 'current', 'label' => 'Not yet due', 'type' => 'money'],
            ['key' => 'days30', 'label' => '1-30 days', 'type' => 'money'], ['key' => 'days60', 'label' => '31-60 days', 'type' => 'money'],
            ['key' => 'days90', 'label' => '60+ days', 'type' => 'money'], ['key' => 'total', 'label' => 'Total owing', 'type' => 'money'],
            ['key' => 'oldest', 'label' => 'Oldest due', 'type' => 'date'],
        ], $rows, ['project' => 'Total', ...ReportResult::sum($rows, ['current', 'days30', 'days60', 'days90', 'total'])]);
    }
}
