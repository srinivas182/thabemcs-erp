<?php

declare(strict_types=1);

namespace App\Domains\Funding\Services;

use App\Domains\Feasibility\Models\Feasibility;
use App\Domains\Feasibility\Services\FeasibilityService;
use App\Domains\Funding\Enums\FundingStatus;
use App\Domains\Funding\Models\FundingMovement;
use App\Domains\Funding\Models\FundingSource;
use App\Domains\Projects\Models\Project;

/**
 * Compares the funding secured for a project against what the approved feasibility says it needs.
 */
final class FundingSummary
{
    public function __construct(private readonly FeasibilityService $feasibility) {}

    /**
     * @return array{requirement: float|null, requirementSource: string|null, committed: float, received: float, repaid: float, gap: float|null}
     */
    public function forProject(Project $project): array
    {
        $baseline = Feasibility::query()->with('lines')->where('project_id', $project->id)->where('is_baseline', true)->first();
        $requirement = $baseline ? (float) $this->feasibility->results($baseline)['peakFunding'] : null;

        $sourceIds = FundingSource::query()->where('project_id', $project->id)->pluck('id');
        $committed = (float) FundingSource::query()->where('project_id', $project->id)
            ->whereIn('status', [FundingStatus::Committed, FundingStatus::Active])->sum('committed_amount');
        $received = (float) FundingMovement::query()->whereIn('funding_source_id', $sourceIds)->where('direction', 'in')->sum('amount');
        $repaid = (float) FundingMovement::query()->whereIn('funding_source_id', $sourceIds)->where('direction', 'out')->sum('amount');

        return [
            'requirement' => $requirement,
            'requirementSource' => $baseline?->name,
            'committed' => round($committed, 2),
            'received' => round($received, 2),
            'repaid' => round($repaid, 2),
            'gap' => $requirement === null ? null : round(max(0.0, $requirement - $committed), 2),
        ];
    }
}
