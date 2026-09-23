<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Programme\Services\ScheduleService;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Risk;
use App\Domains\Reporting\Models\ProjectMetric;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Site\Models\Snag;
use App\Support\Cache\CompanyCache;

/**
 * Recomputes one project's stored figures and critical path. Runs in a queued job, so the cost is
 * paid once per change rather than on every page view.
 */
final class ProjectMetricsService
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly ScheduleService $schedule,
        private readonly CompanyCache $cache,
    ) {}

    public function refresh(Project $project): ProjectMetric
    {
        $budget = 0.0;
        $spent = 0.0;
        foreach (BudgetLine::query()->where('project_id', $project->id)->get() as $line) {
            $f = $this->budgets->figures($line);
            $budget += $f['revised'];
            $spent += $f['committed'] + $f['direct'];
        }
        $used = $budget > 0 ? round($spent / $budget * 100, 1) : null;

        // Critical path: stored on the activities so reports can query it across projects.
        $plan = $this->schedule->calculate($project);
        foreach ($plan['activities'] as $id => $a) {
            ProgrammeActivity::query()->whereKey($id)->update([
                'early_start' => $a['earlyStart'], 'early_finish' => $a['earlyFinish'], 'total_float' => $a['float'], 'is_critical' => $a['critical'],
            ]);
        }
        $behind = count(array_filter($plan['activities'], static fn (array $a): bool => $a['behind']));
        $late = $project->planned_completion_date !== null && $plan['finish'] !== null && $plan['finish'] > $project->planned_completion_date->toDateString();

        $highRisks = Risk::query()->where('project_id', $project->id)->where('status', '!=', 'closed')->whereRaw('likelihood * impact >= 10')->count();
        $incidents = SafetyIncident::query()->where('project_id', $project->id)->where('status', '!=', 'closed')->count();

        // Red: over budget, an open incident or forecast to finish late. Amber: 80%+ used, high risks or activities behind.
        $health = ($used !== null && $used >= 100) || $incidents > 0 || $late ? 'red'
            : (($used !== null && $used >= 80) || $highRisks > 0 || $behind > 0 ? 'amber' : 'green');

        $metric = ProjectMetric::query()->updateOrCreate(['project_id' => $project->id], [
            'budget' => round($budget, 2), 'spent' => round($spent, 2), 'used_percent' => $used,
            'paid' => round((float) SupplierInvoice::query()->where('project_id', $project->id)->where('status', 'paid')->sum('subtotal'), 2),
            'high_risks' => $highRisks, 'open_incidents' => $incidents,
            'open_snags' => Snag::query()->where('project_id', $project->id)->where('status', 'open')->count(),
            'behind_activities' => $behind, 'forecast_finish' => $plan['finish'], 'late' => $late,
            'health' => $health, 'severity' => ['red' => 0, 'amber' => 1, 'green' => 2][$health], 'refreshed_at' => now(),
        ]);

        $this->cache->flush('portfolio');

        return $metric;
    }
}
