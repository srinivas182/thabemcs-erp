<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Feasibility\Models\Feasibility;
use App\Domains\Feasibility\Services\FeasibilityService;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Month-by-month project spend: forecast from the approved feasibility against invoices actually paid (excl. VAT).
 */
final class CashFlowService
{
    public function __construct(private readonly FeasibilityService $feasibility) {}

    /**
     * @return array{start: string|null, months: list<array{month: string, forecast: float, actual: float, forecastCumulative: float, actualCumulative: float}>, baseline: string|null}
     */
    public function forProject(Project $project): array
    {
        $baseline = Feasibility::query()->with('lines')->where('project_id', $project->id)->where('is_baseline', true)->first();
        $start = $project->planned_start_date ?? ($baseline !== null ? $baseline->approved_at : null);
        $start = $start ? Carbon::parse($start)->startOfMonth() : null;

        $forecast = [];
        if ($baseline !== null && $start !== null) {
            foreach ($this->feasibility->results($baseline)['monthly'] as $row) {
                $key = $start->copy()->addMonths($row['month'] - 1)->format('Y-m');
                $forecast[$key] = (float) $row['out'];
            }
        }

        $actual = [];
        SupplierInvoice::query()->where('project_id', $project->id)->where('status', 'paid')->whereNotNull('paid_at')
            ->get(['subtotal', 'paid_at'])
            ->each(function (SupplierInvoice $i) use (&$actual): void {
                $key = $i->paid_at?->timezone('Africa/Johannesburg')->format('Y-m') ?? '';
                $actual[$key] = ($actual[$key] ?? 0.0) + (float) $i->subtotal;
            });

        $keys = array_unique([...array_keys($forecast), ...array_keys($actual)]);
        sort($keys);

        $months = [];
        $fc = 0.0;
        $ac = 0.0;
        foreach ($keys as $key) {
            $fc += $forecast[$key] ?? 0.0;
            $ac += $actual[$key] ?? 0.0;
            $months[] = [
                'month' => $key, 'forecast' => round($forecast[$key] ?? 0.0, 2), 'actual' => round($actual[$key] ?? 0.0, 2),
                'forecastCumulative' => round($fc, 2), 'actualCumulative' => round($ac, 2),
            ];
        }

        return ['start' => $start?->toDateString(), 'months' => $months, 'baseline' => $baseline?->name];
    }
}
