<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Sales\Models\SaleAgreement;
use App\Domains\Sales\Models\SaleUnit;

/**
 * Revenue from sales, excluding VAT.
 *
 * Forecast revenue is what has been sold at the agreed prices plus what is still to sell at today's
 * list prices; earned revenue is what has actually registered in the Deeds Office. Once a project has
 * a stock schedule, these replace the feasibility's revenue line in profitability and cash flow.
 */
final class SalesRevenueService
{
    /**
     * @return array{hasStock: bool, units: int, sold: int, transferred: int, available: int, forecast: float, contracted: float, earned: float, deposits: float, listValue: float}
     */
    public function forProject(Project $project): array
    {
        $units = SaleUnit::query()->where('project_id', $project->id)->where('status', '!=', 'withdrawn')->get();
        if ($units->isEmpty()) {
            return ['hasStock' => false, 'units' => 0, 'sold' => 0, 'transferred' => 0, 'available' => 0,
                'forecast' => 0.0, 'contracted' => 0.0, 'earned' => 0.0, 'deposits' => 0.0, 'listValue' => 0.0];
        }

        $agreements = SaleAgreement::query()->whereIn('sale_unit_id', $units->modelKeys())
            ->whereIn('status', ['conditional', 'unconditional', 'registered'])->get()->keyBy('sale_unit_id');

        $forecast = 0.0;
        $contracted = 0.0;
        $earned = 0.0;
        foreach ($units as $unit) {
            $agreement = $agreements->get($unit->id);
            if ($agreement === null) {
                $forecast += $unit->netPrice();

                continue;
            }
            $net = $agreement->netPrice();
            $forecast += $net;
            $contracted += $net;
            if ($agreement->status === 'registered') {
                $earned += $net;
            }
        }

        return [
            'hasStock' => true,
            'units' => $units->count(),
            'sold' => $agreements->count(),
            'transferred' => $agreements->where('status', 'registered')->count(),
            'available' => $units->where('status', 'available')->count(),
            'forecast' => round($forecast, 2),
            'contracted' => round($contracted, 2),
            'earned' => round($earned, 2),
            'deposits' => round((float) $agreements->whereNotNull('deposit_received_on')->sum(static fn (SaleAgreement $a): float => (float) $a->deposit_amount), 2),
            'listValue' => round((float) $units->sum(static fn (SaleUnit $u): float => $u->netPrice()), 2),
        ];
    }
}
