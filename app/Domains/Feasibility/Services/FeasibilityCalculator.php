<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Services;

use App\Domains\Feasibility\Enums\LineBasis;
use App\Domains\Feasibility\Enums\LineCategory;

/**
 * Development appraisal maths: totals, profit measures, monthly cash flow,
 * peak funding requirement and IRR. Pure functions, no database access.
 *
 * All amounts are ZAR excluding VAT.
 */
final class FeasibilityCalculator
{
    /**
     * @param  list<array{category: LineCategory, basis: LineBasis, amount: float|null, rate: float|null, start_month: int, end_month: int}>  $lines
     * @return array{
     *     revenue: float, cost: float, profit: float,
     *     marginOnRevenue: float|null, profitOnCost: float|null,
     *     peakFunding: float, peakMonth: int|null, irr: float|null,
     *     byCategory: array<string, float>,
     *     monthly: list<array{month: int, in: float, out: float, cumulative: float}>
     * }
     */
    public function calculate(array $lines, int $durationMonths): array
    {
        $duration = max(1, $durationMonths);

        // Percent-based lines depend on the amount-based construction and revenue totals.
        $base = [LineCategory::Construction->value => 0.0, LineCategory::Revenue->value => 0.0];
        foreach ($lines as $line) {
            if ($line['basis'] === LineBasis::Amount && isset($base[$line['category']->value])) {
                $base[$line['category']->value] += (float) ($line['amount'] ?? 0);
            }
        }

        $in = array_fill(1, $duration, 0.0);
        $out = array_fill(1, $duration, 0.0);
        $byCategory = [];
        $revenue = 0.0;
        $cost = 0.0;

        foreach ($lines as $line) {
            $value = match ($line['basis']) {
                LineBasis::Amount => (float) ($line['amount'] ?? 0),
                LineBasis::PercentOfConstruction => $base[LineCategory::Construction->value] * (float) ($line['rate'] ?? 0) / 100,
                LineBasis::PercentOfRevenue => $base[LineCategory::Revenue->value] * (float) ($line['rate'] ?? 0) / 100,
            };

            $category = $line['category']->value;
            $byCategory[$category] = ($byCategory[$category] ?? 0.0) + $value;

            $start = min(max(1, $line['start_month']), $duration);
            $end = min(max($start, $line['end_month']), $duration);
            $perMonth = $value / ($end - $start + 1);

            for ($m = $start; $m <= $end; $m++) {
                if ($line['category']->isRevenue()) {
                    $in[$m] += $perMonth;
                } else {
                    $out[$m] += $perMonth;
                }
            }

            if ($line['category']->isRevenue()) {
                $revenue += $value;
            } else {
                $cost += $value;
            }
        }

        $monthly = [];
        $cumulative = 0.0;
        $lowest = 0.0;
        $lowestMonth = null;
        $flows = [];

        for ($m = 1; $m <= $duration; $m++) {
            $net = $in[$m] - $out[$m];
            $flows[] = $net;
            $cumulative += $net;
            if ($cumulative < $lowest) {
                $lowest = $cumulative;
                $lowestMonth = $m;
            }
            $monthly[] = ['month' => $m, 'in' => round($in[$m], 2), 'out' => round($out[$m], 2), 'cumulative' => round($cumulative, 2)];
        }

        $profit = $revenue - $cost;
        $monthlyIrr = $this->irr($flows);

        return [
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'profit' => round($profit, 2),
            'marginOnRevenue' => $revenue > 0 ? round($profit / $revenue * 100, 2) : null,
            'profitOnCost' => $cost > 0 ? round($profit / $cost * 100, 2) : null,
            'peakFunding' => round(-$lowest, 2),
            'peakMonth' => $lowestMonth,
            'irr' => $monthlyIrr === null ? null : round(((1 + $monthlyIrr) ** 12 - 1) * 100, 2),
            'byCategory' => array_map(static fn (float $v): float => round($v, 2), $byCategory),
            'monthly' => $monthly,
        ];
    }

    /**
     * Monthly internal rate of return by bisection. Null when cash flows never change sign.
     *
     * @param  list<float>  $flows
     */
    public function irr(array $flows): ?float
    {
        $hasNegative = false;
        $hasPositive = false;
        foreach ($flows as $f) {
            $hasNegative = $hasNegative || $f < 0;
            $hasPositive = $hasPositive || $f > 0;
        }
        if (! $hasNegative || ! $hasPositive) {
            return null;
        }

        $npv = static function (float $rate) use ($flows): float {
            $total = 0.0;
            foreach ($flows as $t => $f) {
                $total += $f / ((1 + $rate) ** $t);
            }

            return $total;
        };

        $low = -0.99;
        $high = 1.0;
        $npvLow = $npv($low);
        $npvHigh = $npv($high);

        if ($npvLow * $npvHigh > 0) {
            return null;
        }

        for ($i = 0; $i < 200; $i++) {
            $mid = ($low + $high) / 2;
            $npvMid = $npv($mid);

            if ($npvMid === 0.0 || ($high - $low) < 1e-10) {
                return $mid;
            }

            if ($npvLow * $npvMid < 0) {
                $high = $mid;
            } else {
                $low = $mid;
                $npvLow = $npvMid;
            }
        }

        return ($low + $high) / 2;
    }
}
