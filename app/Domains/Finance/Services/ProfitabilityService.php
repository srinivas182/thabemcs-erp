<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Feasibility\Models\Feasibility;
use App\Domains\Feasibility\Services\FeasibilityService;
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Projects\Models\Project;

/**
 * Project profitability: the approved feasibility against today's forecast.
 *
 * Forecast final cost per cost code is the larger of the revised budget and what is already committed or
 * spent, so overruns show immediately. Revenue comes from the feasibility until the Sales module records sales.
 */
final class ProfitabilityService
{
    public function __construct(private readonly BudgetService $budgets, private readonly FeasibilityService $feasibility) {}

    /**
     * @return array{hasBaseline: bool, baseline: array{revenue: float, cost: float, profit: float, margin: float|null}|null, forecast: array{revenue: float, cost: float, profit: float, margin: float|null}, costToDate: float, overruns: list<array{code: string, description: string, over: float}>}
     */
    public function forProject(Project $project): array
    {
        $baseline = Feasibility::query()->with('lines')->where('project_id', $project->id)->where('is_baseline', true)->first();
        $base = null;
        $revenue = 0.0;
        if ($baseline !== null) {
            $r = $this->feasibility->results($baseline);
            $revenue = (float) $r['revenue'];
            $base = ['revenue' => $revenue, 'cost' => (float) $r['cost'], 'profit' => (float) $r['profit'], 'margin' => $revenue > 0 ? round((float) $r['profit'] / $revenue * 100, 1) : null];
        }

        $cost = 0.0;
        $overruns = [];
        foreach (BudgetLine::query()->where('project_id', $project->id)->orderBy('code')->get() as $line) {
            $f = $this->budgets->figures($line);
            $spent = $f['committed'] + $f['direct'];
            $cost += max($f['revised'], $spent);
            if ($spent > $f['revised']) {
                $overruns[] = ['code' => $f['code'], 'description' => $f['description'], 'over' => round($spent - $f['revised'], 2)];
            }
        }

        $profit = $revenue - $cost;

        return [
            'hasBaseline' => $baseline !== null,
            'baseline' => $base,
            'forecast' => ['revenue' => round($revenue, 2), 'cost' => round($cost, 2), 'profit' => round($profit, 2), 'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null],
            'costToDate' => round((float) SupplierInvoice::query()->where('project_id', $project->id)->where('status', 'paid')->sum('subtotal'), 2),
            'overruns' => $overruns,
        ];
    }
}
