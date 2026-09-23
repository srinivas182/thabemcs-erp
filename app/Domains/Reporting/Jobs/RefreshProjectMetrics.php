<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Jobs;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Platform\Models\Company;
use App\Domains\Programme\Models\ActivityDependency;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Services\ProjectMetricsService;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Recalculate one project's metrics. Unique per project while queued, so a burst of changes (an order
 * with 40 lines, a programme import) causes one recalculation, not forty.
 */
final class RefreshProjectMetrics implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 120;

    public int $tries = 3;

    public function __construct(public readonly int $companyId, public readonly int $projectId)
    {
        $this->onQueue('metrics');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->projectId;
    }

    public function handle(ProjectMetricsService $metrics, CurrentCompany $context): void
    {
        $company = Company::query()->find($this->companyId);
        if ($company === null) {
            return;
        }
        $context->runFor($company, function () use ($metrics): void {
            $project = Project::query()->withoutGlobalScope(CompanyScope::class)->where('company_id', $this->companyId)->find($this->projectId);
            if ($project !== null) {
                $metrics->refresh($project);
            }
        });
    }

    /**
     * Queue a refresh for whatever project a changed record belongs to.
     */
    public static function forModel(Model $model): void
    {
        $projectId = match (true) {
            $model instanceof Project => $model->getKey(),
            $model instanceof ActivityDependency => ProgrammeActivity::query()->whereKey($model->successor_id)->value('project_id'),
            default => $model->getAttribute('project_id') ?? ($model->getAttribute('budget_line_id') ? BudgetLine::query()->whereKey($model->getAttribute('budget_line_id'))->value('project_id') : null),
        };
        $companyId = $model->getAttribute('company_id');
        if ($projectId !== null && $companyId !== null) {
            self::dispatch((int) $companyId, (int) $projectId);
        }
    }
}
