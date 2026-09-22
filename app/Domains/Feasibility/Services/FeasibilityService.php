<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Services;

use App\Domains\Feasibility\Enums\FeasibilityStatus;
use App\Domains\Feasibility\Enums\LineBasis;
use App\Domains\Feasibility\Enums\LineCategory;
use App\Domains\Feasibility\Models\Feasibility;
use App\Domains\Feasibility\Models\FeasibilityLine;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class FeasibilityService
{
    public function __construct(private readonly FeasibilityCalculator $calculator) {}

    /**
     * Create a scenario, either from the standard SA appraisal template or as a copy of another scenario.
     */
    public function create(Project $project, string $name, int $durationMonths, ?int $units, ?Feasibility $copyFrom = null): Feasibility
    {
        return DB::transaction(function () use ($project, $name, $durationMonths, $units, $copyFrom): Feasibility {
            $feasibility = Feasibility::query()->create([
                'project_id' => $project->id,
                'name' => $name,
                'duration_months' => $durationMonths,
                'units' => $units,
            ]);

            $lines = $copyFrom
                ? $copyFrom->lines->map(static fn (FeasibilityLine $l): array => $l->only(['category', 'description', 'basis', 'amount', 'rate', 'start_month', 'end_month']))->all()
                : $this->template($durationMonths);

            foreach (array_values($lines) as $i => $line) {
                FeasibilityLine::query()->create([...$line, 'feasibility_id' => $feasibility->id, 'sort' => $i]);
            }

            return $feasibility;
        });
    }

    /**
     * Replace all lines. Approved scenarios are locked.
     *
     * @param  list<array{category: string, description: string, basis: string, amount: float|string|null, rate: float|string|null, start_month: int, end_month: int}>  $lines
     */
    public function saveLines(Feasibility $feasibility, array $lines, int $durationMonths, ?int $units): void
    {
        DB::transaction(function () use ($feasibility, $lines, $durationMonths, $units): void {
            $feasibility->update(['duration_months' => $durationMonths, 'units' => $units]);
            $feasibility->lines()->delete();

            foreach ($lines as $i => $line) {
                FeasibilityLine::query()->create([...$line, 'feasibility_id' => $feasibility->id, 'sort' => $i]);
            }
        });
    }

    /**
     * Approve a scenario and make it the project's baseline (only one baseline per project).
     */
    public function approve(Feasibility $feasibility, User $approver): void
    {
        DB::transaction(function () use ($feasibility, $approver): void {
            Feasibility::query()->where('project_id', $feasibility->project_id)->update(['is_baseline' => false]);

            $feasibility->forceFill([
                'status' => FeasibilityStatus::Approved,
                'is_baseline' => true,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function results(Feasibility $feasibility): array
    {
        $lines = $feasibility->lines->map(static fn (FeasibilityLine $l): array => [
            'category' => $l->category,
            'basis' => $l->basis,
            'amount' => $l->amount === null ? null : (float) $l->amount,
            'rate' => $l->rate === null ? null : (float) $l->rate,
            'start_month' => $l->start_month,
            'end_month' => $l->end_month,
        ])->values()->all();

        return $this->calculator->calculate(array_values($lines), $feasibility->duration_months);
    }

    /**
     * Standard South African development appraisal headings with typical timing.
     * Amounts start at zero; percentage lines carry common starting rates (see docs/assumptions.md FE3).
     *
     * @return list<array{category: LineCategory, description: string, basis: LineBasis, amount: float|null, rate: float|null, start_month: int, end_month: int}>
     */
    public function template(int $duration): array
    {
        $d = max(6, $duration);
        $buildStart = 4;
        $buildEnd = max($buildStart, $d - 4);
        $salesStart = max(1, $d - 6);

        $line = static fn (LineCategory $c, string $desc, LineBasis $b, ?float $rate, int $start, int $end): array => [
            'category' => $c, 'description' => $desc, 'basis' => $b,
            'amount' => $b === LineBasis::Amount ? 0.0 : null, 'rate' => $rate,
            'start_month' => $start, 'end_month' => $end,
        ];

        return [
            $line(LineCategory::Land, 'Land purchase price', LineBasis::Amount, null, 1, 1),
            $line(LineCategory::Acquisition, 'Transfer duty', LineBasis::Amount, null, 1, 2),
            $line(LineCategory::Acquisition, 'Conveyancing and bond registration', LineBasis::Amount, null, 1, 2),
            $line(LineCategory::ProfessionalFees, 'Professional team fees', LineBasis::PercentOfConstruction, 12.0, 1, $buildEnd),
            $line(LineCategory::Municipal, 'Bulk services contributions and plan fees', LineBasis::Amount, null, 2, 3),
            $line(LineCategory::Municipal, 'NHBRC enrolment fees', LineBasis::Amount, null, 3, 3),
            $line(LineCategory::Construction, 'Construction contract', LineBasis::Amount, null, $buildStart, $buildEnd),
            $line(LineCategory::Contingency, 'Construction contingency', LineBasis::PercentOfConstruction, 5.0, $buildStart, $buildEnd),
            $line(LineCategory::Marketing, 'Marketing and agents\' commission', LineBasis::PercentOfRevenue, 5.0, $salesStart, $d),
            $line(LineCategory::Finance, 'Interest and raising fees', LineBasis::Amount, null, $buildStart, $d),
            $line(LineCategory::Revenue, 'Unit sales', LineBasis::Amount, null, $salesStart, $d),
        ];
    }
}
