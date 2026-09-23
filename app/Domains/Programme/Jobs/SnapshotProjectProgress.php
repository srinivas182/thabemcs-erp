<?php

declare(strict_types=1);

namespace App\Domains\Programme\Jobs;

use App\Domains\Platform\Models\Company;
use App\Domains\Programme\Services\EarnedValueService;
use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Weekly earned-value reading for one project, queued per project so the whole portfolio is measured
 * in parallel.
 */
final class SnapshotProjectProgress implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $companyId, public readonly int $projectId)
    {
        $this->onQueue('metrics');
    }

    public function uniqueId(): string
    {
        return (string) $this->projectId;
    }

    public function handle(EarnedValueService $evm, CurrentCompany $context): void
    {
        $company = Company::query()->find($this->companyId);
        if ($company === null) {
            return;
        }
        $context->runFor($company, function () use ($evm): void {
            $project = Project::query()->withoutGlobalScope(CompanyScope::class)->where('company_id', $this->companyId)->find($this->projectId);
            if ($project !== null) {
                $evm->snapshot($project);
            }
        });
    }

    /**
     * Queue a reading for every live project in every active company.
     */
    public static function fanOut(CurrentCompany $context): int
    {
        $queued = 0;
        Company::query()->where('status', 'active')->each(function (Company $company) use ($context, &$queued): void {
            $context->runFor($company, function () use ($company, &$queued): void {
                Project::query()->where('status', 'active')->select(['id'])->chunkById(500, function ($projects) use ($company, &$queued): void {
                    foreach ($projects as $project) {
                        self::dispatch((int) $company->getKey(), (int) $project->id);
                        $queued++;
                    }
                });
            });
        });

        return $queued;
    }
}
