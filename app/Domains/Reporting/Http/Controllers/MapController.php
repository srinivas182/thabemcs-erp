<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Reporting\Models\ProjectMetric;
use App\Domains\Reporting\Services\PortfolioService;
use App\Models\User;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Command centre. Every live project is a compact map point (clustered in the browser); the side list
 * shows the 100 needing attention most. All figures come from project_metrics, so the page costs a fixed
 * number of queries whatever the size of the portfolio.
 */
final class MapController
{
    public function __construct(private readonly PortfolioService $portfolio, private readonly CurrentCompany $context) {}

    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $group = $this->context->get() === null;
        if ($group) {
            abort_unless($user->is_super_admin, 403);
        } else {
            Gate::authorize('view-financial-reports');
        }

        $query = ProjectMetric::query()->join('projects', 'projects.id', '=', 'project_metrics.project_id')
            ->whereIn('projects.status', [ProjectStatus::Active->value, ProjectStatus::OnHold->value]);
        if ($group) {
            $query->withoutGlobalScope(CompanyScope::class);
        }

        $counts = (clone $query)->selectRaw('project_metrics.health, count(*) as n')->groupBy('project_metrics.health')->toBase()->pluck('n', 'health');
        $points = (clone $query)->whereNotNull('projects.latitude')->whereNotNull('projects.longitude')
            ->toBase()->get(['projects.ulid', 'projects.code', 'projects.latitude', 'projects.longitude', 'project_metrics.health'])
            ->map(static fn ($r): array => [$r->ulid, $r->code, round((float) $r->latitude, 5), round((float) $r->longitude, 5), $r->health])->values();

        if ($group) {
            $companies = Company::query()->pluck('name', 'id');
            $list = ProjectMetric::query()->withoutGlobalScope(CompanyScope::class)->with(['project' => fn ($q) => $q->withoutGlobalScope(CompanyScope::class)])
                ->whereHas('project', fn ($q) => $q->withoutGlobalScope(CompanyScope::class)->whereIn('status', [ProjectStatus::Active, ProjectStatus::OnHold]))
                ->orderBy('severity')->orderByDesc('used_percent')->limit(PortfolioService::LIST_LIMIT)->get()
                ->map(static fn (ProjectMetric $m): array => [...PortfolioService::row($m, true), 'company' => $companies[$m->getAttribute('company_id')] ?? null])->values()->all();
        } else {
            $list = $this->portfolio->attentionList(PortfolioService::LIST_LIMIT, true);
        }

        return Inertia::render('reports/map', [
            'points' => $points,
            'projects' => $list,
            'counts' => ['red' => (int) ($counts['red'] ?? 0), 'amber' => (int) ($counts['amber'] ?? 0), 'green' => (int) ($counts['green'] ?? 0)],
            'group' => $group,
        ]);
    }
}
