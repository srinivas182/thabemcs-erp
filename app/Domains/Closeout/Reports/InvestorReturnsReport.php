<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Reports;

use App\Domains\Closeout\Services\DistributionService;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

/**
 * What each investor has put in, been paid and is still owed, per project.
 */
final class InvestorReturnsReport implements Report
{
    public function __construct(private readonly DistributionService $distributions) {}

    public function key(): string
    {
        return 'investor-returns';
    }

    public function title(): string
    {
        return 'Investor returns';
    }

    public function description(): string
    {
        return 'Capital contributed, capital still owed, preferred return owed and total paid, per investor and project.';
    }

    public function gate(): string
    {
        return 'view-financial-reports';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $rows = [];
        $projects = Project::query()
            ->when($filters->project, fn ($q) => $q->whereKey($filters->project?->id))
            ->whereIn('status', [ProjectStatus::Active, ProjectStatus::OnHold, ProjectStatus::Completed])
            ->orderBy('code')->get();

        foreach ($projects as $project) {
            foreach ($this->distributions->positions($project) as $position) {
                $rows[] = [
                    'project' => $project->code, 'investor' => $position['investor'], 'source' => $position['source'],
                    'contributed' => $position['contributed'], 'capital' => $position['capitalOutstanding'],
                    'preferred' => $position['preferredOutstanding'], 'paid' => $position['paidToDate'],
                ];
            }
        }

        return new ReportResult($this->title(), $filters->describe(false, false), [
            ['key' => 'project', 'label' => 'Project', 'type' => 'text'], ['key' => 'investor', 'label' => 'Investor', 'type' => 'text'],
            ['key' => 'source', 'label' => 'Funding', 'type' => 'text'], ['key' => 'contributed', 'label' => 'Put in', 'type' => 'money'],
            ['key' => 'capital', 'label' => 'Capital owed', 'type' => 'money'], ['key' => 'preferred', 'label' => 'Preferred owed', 'type' => 'money'],
            ['key' => 'paid', 'label' => 'Paid to date', 'type' => 'money'],
        ], $rows, ['project' => 'Total', ...ReportResult::sum($rows, ['contributed', 'capital', 'preferred', 'paid'])]);
    }
}
