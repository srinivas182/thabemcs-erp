<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Services\LeaseService;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;
use Illuminate\Support\Carbon;

/**
 * Rent roll: every active lease with its current rent, escalation, expiry and arrears.
 */
final class RentRollReport implements Report
{
    public function __construct(private readonly LeaseService $leases) {}

    public function key(): string
    {
        return 'rent-roll';
    }

    public function title(): string
    {
        return 'Rent roll';
    }

    public function description(): string
    {
        return 'Active leases with the rent now payable, escalation, expiry date, deposit held and arrears.';
    }

    public function gate(): string
    {
        return 'view-rentals';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $today = Carbon::today('Africa/Johannesburg');

        $rows = Lease::query()->with(['unit.project:id,code', 'tenant:id,name'])->where('status', 'active')
            ->when($filters->project, fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('project_id', $filters->project?->id)))
            ->orderBy('number')->get()
            ->map(fn (Lease $lease): array => [
                'project' => $lease->unit->project->code, 'unit' => $lease->unit->reference, 'tenant' => $lease->tenant->name,
                'type' => ucfirst($lease->type), 'starts' => $lease->starts_on->toDateString(),
                'ends' => $lease->month_to_month ? null : $lease->ends_on?->toDateString(),
                'rent' => $lease->rentAt($today), 'escalation' => (float) $lease->escalation_percent,
                'deposit' => round((float) $lease->deposit_amount + (float) $lease->deposit_interest, 2),
                'arrears' => $this->leases->arrears($lease, $today)['total'],
            ])->values()->all();

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
            ['key' => 'tenant', 'label' => 'Tenant', 'type' => 'text'], ['key' => 'type', 'label' => 'Type', 'type' => 'text'],
            ['key' => 'starts', 'label' => 'From', 'type' => 'date'], ['key' => 'ends', 'label' => 'To', 'type' => 'date'],
            ['key' => 'rent', 'label' => 'Rent a month', 'type' => 'money'], ['key' => 'escalation', 'label' => 'Escalation', 'type' => 'percent'],
            ['key' => 'deposit', 'label' => 'Deposit held', 'type' => 'money'], ['key' => 'arrears', 'label' => 'Arrears', 'type' => 'money'],
        ], $rows, ['project' => 'Total', ...ReportResult::sum($rows, ['rent', 'deposit', 'arrears'])],
            count($rows).' active leases. Blank "To" means month to month.');
    }
}
