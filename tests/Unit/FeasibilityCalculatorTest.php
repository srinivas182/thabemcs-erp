<?php

declare(strict_types=1);

use App\Domains\Feasibility\Enums\LineBasis as B;
use App\Domains\Feasibility\Enums\LineCategory as C;
use App\Domains\Feasibility\Services\FeasibilityCalculator;

function appraisal(): array
{
    return (new FeasibilityCalculator)->calculate([
        ['category' => C::Land, 'basis' => B::Amount, 'amount' => 10_000_000.0, 'rate' => null, 'start_month' => 1, 'end_month' => 1],
        ['category' => C::Construction, 'basis' => B::Amount, 'amount' => 40_000_000.0, 'rate' => null, 'start_month' => 4, 'end_month' => 15],
        ['category' => C::ProfessionalFees, 'basis' => B::PercentOfConstruction, 'amount' => null, 'rate' => 12.0, 'start_month' => 1, 'end_month' => 15],
        ['category' => C::Marketing, 'basis' => B::PercentOfRevenue, 'amount' => null, 'rate' => 5.0, 'start_month' => 13, 'end_month' => 18],
        ['category' => C::Revenue, 'basis' => B::Amount, 'amount' => 72_000_000.0, 'rate' => null, 'start_month' => 13, 'end_month' => 18],
    ], 18);
}

it('works out percentage lines from construction and revenue', function (): void {
    $r = appraisal();

    expect($r['byCategory']['professional_fees'])->toBe(4_800_000.0)
        ->and($r['byCategory']['marketing'])->toBe(3_600_000.0)
        ->and($r['cost'])->toBe(58_400_000.0);
});

it('calculates profit, margin on revenue and profit on cost', function (): void {
    $r = appraisal();

    expect($r['profit'])->toBe(13_600_000.0)
        ->and($r['marginOnRevenue'])->toBe(18.89)
        ->and($r['profitOnCost'])->toBe(23.29);
});

it('finds the peak funding requirement from the cumulative cash flow', function (): void {
    $r = appraisal();

    expect($r['peakMonth'])->toBe(12)
        ->and($r['peakFunding'])->toBe(43_840_000.0)
        ->and($r['monthly'])->toHaveCount(18)
        ->and(end($r['monthly'])['cumulative'])->toBe(13_600_000.0);
});

it('calculates IRR and returns null when cash flows never turn positive', function (): void {
    $calc = new FeasibilityCalculator;

    expect(round($calc->irr([-100.0, 110.0]), 6))->toBe(0.1)
        ->and($calc->irr([-100.0, -50.0]))->toBeNull()
        ->and(appraisal()['irr'])->toBeGreaterThan(0.0);
});
