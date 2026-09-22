<?php

declare(strict_types=1);

namespace App\Domains\Programme\Services;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Programme\Models\ProgressSnapshot;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Earned value: planned value (PV) from the programme, earned value (EV) from recorded progress,
 * actual cost (AC) from approved supplier invoices, all excl. VAT.
 *
 * Budget at completion (BAC) is the revised project budget. It is spread over activities by their own
 * budget values where entered, otherwise in proportion to their durations.
 */
final class EarnedValueService
{
    public function __construct(private readonly ScheduleService $schedule, private readonly BudgetService $budgets) {}

    /**
     * @return array{bac: float, pv: float, ev: float, ac: float, spi: float|null, cpi: float|null, eac: float|null, vac: float|null, curve: list<array{month: string, pv: float}>, weighting: string}
     */
    public function measure(Project $project, ?Carbon $on = null): array
    {
        $on ??= Carbon::today('Africa/Johannesburg');
        $activities = ProgrammeActivity::query()->where('project_id', $project->id)->get();
        $plan = $this->schedule->calculate($project);

        $bac = 0.0;
        foreach (BudgetLine::query()->where('project_id', $project->id)->get() as $line) {
            $bac += $this->budgets->figures($line)['revised'];
        }

        $entered = (float) $activities->sum(static fn (ProgrammeActivity $a): float => (float) $a->budget_value);
        $totalDays = (int) $activities->sum('duration_days');
        $weighting = $entered > 0 ? 'activity budgets' : 'activity durations';
        $weights = [];
        foreach ($activities as $a) {
            $weights[$a->id] = $entered > 0
                ? ($bac > 0 ? (float) $a->budget_value / $entered * $bac : (float) $a->budget_value)
                : ($totalDays > 0 ? $a->duration_days / $totalDays * $bac : 0.0);
        }
        if ($entered > 0 && $bac <= 0) {
            $bac = $entered;
        }

        $pv = 0.0;
        $ev = 0.0;
        foreach ($activities as $a) {
            $dates = $plan['activities'][$a->id] ?? null;
            if ($dates === null) {
                continue;
            }
            $pv += $weights[$a->id] * $this->plannedFraction($a, $dates['earlyStart'], $dates['earlyFinish'], $on);
            $ev += $weights[$a->id] * $a->percent_complete / 100;
        }

        $ac = (float) SupplierInvoice::query()->where('project_id', $project->id)->whereIn('status', ['approved', 'scheduled', 'paid'])
            ->whereDate('invoice_date', '<=', $on->toDateString())->sum('subtotal');

        $spi = $pv > 0 ? round($ev / $pv, 2) : null;
        $cpi = $ac > 0 ? round($ev / $ac, 2) : null;
        $eac = $cpi !== null && $cpi > 0 ? round($bac / $cpi, 2) : null;

        return [
            'bac' => round($bac, 2), 'pv' => round($pv, 2), 'ev' => round($ev, 2), 'ac' => round($ac, 2),
            'spi' => $spi, 'cpi' => $cpi, 'eac' => $eac, 'vac' => $eac !== null ? round($bac - $eac, 2) : null,
            'curve' => $this->curve($activities->all(), $plan['activities'], $weights), 'weighting' => $weighting,
        ];
    }

    /**
     * Record this week's reading (used for the history lines on the S-curve).
     */
    public function snapshot(Project $project, ?Carbon $on = null): ?ProgressSnapshot
    {
        $on ??= Carbon::today('Africa/Johannesburg');
        if (! ProgrammeActivity::query()->where('project_id', $project->id)->exists()) {
            return null;
        }
        $m = $this->measure($project, $on);

        return ProgressSnapshot::query()->updateOrCreate(
            ['project_id' => $project->id, 'taken_on' => $on->toDateString()],
            ['planned_value' => $m['pv'], 'earned_value' => $m['ev'], 'actual_cost' => $m['ac']],
        );
    }

    private function plannedFraction(ProgrammeActivity $a, string $start, string $finish, Carbon $on): float
    {
        if ($on->toDateString() < $start) {
            return 0.0;
        }
        if ($on->toDateString() >= $finish || $a->duration_days === 0) {
            return 1.0;
        }
        $done = $this->schedule->workingDaysBetween(Carbon::parse($start), $on);

        return min(1.0, $done / max(1, $a->duration_days));
    }

    /**
     * Planned value at the end of each month across the programme.
     *
     * @param  array<int, ProgrammeActivity>  $activities
     * @param  array<int, array{earlyStart: string, earlyFinish: string, float: int, critical: bool, behind: bool}>  $dates
     * @param  array<int, float>  $weights
     * @return list<array{month: string, pv: float}>
     */
    private function curve(array $activities, array $dates, array $weights): array
    {
        if ($dates === []) {
            return [];
        }
        $first = Carbon::parse(min(array_column($dates, 'earlyStart')))->startOfMonth();
        $last = Carbon::parse(max(array_column($dates, 'earlyFinish')))->endOfMonth();
        $points = [];
        for ($m = $first->copy(); $m->lessThanOrEqualTo($last) && count($points) < 120; $m->addMonthNoOverflow()) {
            $end = $m->copy()->endOfMonth();
            $pv = 0.0;
            foreach ($activities as $a) {
                if (isset($dates[$a->id])) {
                    $pv += $weights[$a->id] * $this->plannedFraction($a, $dates[$a->id]['earlyStart'], $dates[$a->id]['earlyFinish'], $end);
                }
            }
            $points[] = ['month' => $m->format('Y-m'), 'pv' => round($pv, 2)];
        }

        return $points;
    }
}
