<?php

declare(strict_types=1);

namespace App\Domains\Programme\Http\Controllers;

use App\Domains\Finance\Services\ProfitabilityService;
use App\Domains\Programme\Models\ProgressSnapshot;
use App\Domains\Programme\Services\EarnedValueService;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Project performance: earned value (schedule and cost) and profitability against the feasibility.
 */
final class PerformanceController
{
    public function __construct(private readonly EarnedValueService $evm, private readonly ProfitabilityService $profit) {}

    public function show(Project $project): Response
    {
        Gate::authorize('view-financial-reports');

        return Inertia::render('projects/performance', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'evm' => $this->evm->measure($project),
            'history' => ProgressSnapshot::query()->where('project_id', $project->id)->orderBy('taken_on')->get()
                ->map(static fn (ProgressSnapshot $s): array => ['date' => $s->taken_on->toDateString(), 'pv' => (float) $s->planned_value, 'ev' => (float) $s->earned_value, 'ac' => (float) $s->actual_cost])->values(),
            'profitability' => $this->profit->forProject($project),
        ]);
    }
}
