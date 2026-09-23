<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Models\ProjectMetric;
use App\Domains\Safety\Enums\IncidentType;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Support\Cache\CompanyCache;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Portfolio figures for the executive dashboard: one row per live project, company totals,
 * and (for the Super Admin) a row per company across the group.
 */
/**
 * Portfolio figures from the precomputed project_metrics table: a fixed number of queries whatever the
 * number of projects. Totals are cached per company for five minutes and cleared whenever a project's
 * metrics are refreshed.
 */
final class PortfolioService
{
    /** Projects listed on the dashboard, those needing attention first. */
    public const int LIST_LIMIT = 100;

    public function __construct(private readonly CompanyCache $cache) {}

    /**
     * @return array{projects: list<array<string, mixed>>, totals: array<string, float|int|null>, listed: int}
     */
    public function forCurrentCompany(): array
    {
        return $this->cache->remember('portfolio', 'dashboard', 300, fn (): array => [
            'projects' => $this->attentionList(self::LIST_LIMIT),
            'totals' => $this->totals(),
            'listed' => self::LIST_LIMIT,
        ]);
    }

    /**
     * Live projects with their stored figures, those needing attention first.
     *
     * @return list<array<string, mixed>>
     */
    public function attentionList(int $limit, bool $withLocation = false): array
    {
        return array_values($this->live()->with('project')
            ->orderBy('severity')->orderByDesc('used_percent')->limit($limit)->get()
            ->map(static fn (ProjectMetric $m): array => self::row($m, $withLocation))->all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(ProjectMetric $m, bool $withLocation = false): array
    {
        /** @var Project $p */
        $p = $m->getRelation('project');

        return [
            'id' => $p->ulid, 'code' => $p->code, 'name' => $p->name, 'stage' => $p->stage->label(), 'stageNumber' => $p->stage->position(),
            'status' => $p->status->value, 'budget' => (float) $m->budget, 'spent' => (float) $m->spent,
            'used' => $m->used_percent === null ? null : (float) $m->used_percent, 'paid' => (float) $m->paid,
            'highRisks' => $m->high_risks, 'openIncidents' => $m->open_incidents, 'openSnags' => $m->open_snags,
            'behind' => $m->behind_activities, 'late' => $m->late, 'forecastFinish' => $m->forecast_finish?->toDateString(), 'health' => $m->health,
            ...($withLocation ? ['town' => $p->town, 'lat' => $p->latitude === null ? null : (float) $p->latitude, 'lng' => $p->longitude === null ? null : (float) $p->longitude] : []),
        ];
    }

    /**
     * @return array<string, float|int|null>
     */
    public function totals(): array
    {
        $sum = $this->live()->selectRaw('count(*) as projects, coalesce(sum(budget),0) as budget, coalesce(sum(spent),0) as spent, coalesce(sum(paid),0) as paid,
            coalesce(sum(high_risks),0) as high_risks, coalesce(sum(open_incidents),0) as open_incidents,
            coalesce(sum(case when health = \'red\' then 1 else 0 end),0) as red, coalesce(sum(case when health = \'amber\' then 1 else 0 end),0) as amber')
            ->toBase()->first();
        $lastLti = SafetyIncident::query()->whereIn('type', [IncidentType::LostTime->value, IncidentType::Fatality->value])->max('occurred_at');

        return [
            'projects' => (int) ($sum->projects ?? 0),
            'budget' => round((float) ($sum->budget ?? 0), 2),
            'spent' => round((float) ($sum->spent ?? 0), 2),
            'paid' => round((float) ($sum->paid ?? 0), 2),
            'highRisks' => (int) ($sum->high_risks ?? 0),
            'openIncidents' => (int) ($sum->open_incidents ?? 0),
            'red' => (int) ($sum->red ?? 0),
            'amber' => (int) ($sum->amber ?? 0),
            'pendingApprovals' => ApprovalRequest::query()->where('status', 'pending')->count(),
            'owedToSuppliers' => round((float) SupplierInvoice::query()->whereIn('status', ['approved', 'scheduled'])->sum('total'), 2),
            'daysSinceLti' => $lastLti ? (int) Carbon::parse((string) $lastLti)->diffInDays(now()) : null,
        ];
    }

    /**
     * Group view for the Super Admin: one grouped query across companies, not a loop per company.
     *
     * @return list<array<string, mixed>>
     */
    public function forGroup(): array
    {
        $figures = ProjectMetric::query()->withoutGlobalScope(CompanyScope::class)
            ->join('projects', 'projects.id', '=', 'project_metrics.project_id')
            ->whereIn('projects.status', [ProjectStatus::Active->value, ProjectStatus::OnHold->value])
            ->groupBy('project_metrics.company_id')
            ->select('project_metrics.company_id', DB::raw('count(*) as projects'), DB::raw('sum(project_metrics.budget) as budget'), DB::raw('sum(project_metrics.spent) as spent'),
                DB::raw('sum(project_metrics.paid) as paid'), DB::raw('sum(project_metrics.high_risks) as high_risks'), DB::raw('sum(project_metrics.open_incidents) as open_incidents'))
            ->toBase()->get()->keyBy('company_id');

        return array_values(Company::query()->where('status', 'active')->orderBy('name')->get()->map(static function (Company $c) use ($figures): array {
            $f = $figures->get($c->getKey());

            return [
                'id' => $c->ulid, 'name' => $c->name, 'projects' => (int) ($f->projects ?? 0), 'budget' => round((float) ($f->budget ?? 0), 2),
                'spent' => round((float) ($f->spent ?? 0), 2), 'paid' => round((float) ($f->paid ?? 0), 2),
                'highRisks' => (int) ($f->high_risks ?? 0), 'openIncidents' => (int) ($f->open_incidents ?? 0),
            ];
        })->all());
    }

    /**
     * @return Builder<ProjectMetric>
     */
    private function live(): Builder
    {
        return ProjectMetric::query()->whereHas('project', static fn (Builder $q) => $q->whereIn('status', [ProjectStatus::Active, ProjectStatus::OnHold]));
    }
}
