<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Risk;
use App\Domains\Safety\Enums\IncidentType;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Site\Models\Snag;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;

/**
 * Portfolio figures for the executive dashboard: one row per live project, company totals,
 * and (for the Super Admin) a row per company across the group.
 */
final class PortfolioService
{
    public function __construct(private readonly BudgetService $budgets, private readonly CurrentCompany $context) {}

    /**
     * @return array{projects: list<array<string, mixed>>, totals: array<string, float|int|null>}
     */
    public function forCurrentCompany(): array
    {
        $projects = Project::query()->whereIn('status', [ProjectStatus::Active, ProjectStatus::OnHold])->orderBy('code')->get();
        $rows = [];

        foreach ($projects as $p) {
            $revised = 0.0;
            $spent = 0.0;
            foreach (BudgetLine::query()->where('project_id', $p->id)->get() as $line) {
                $f = $this->budgets->figures($line);
                $revised += $f['revised'];
                $spent += $f['committed'] + $f['direct'];
            }

            $rows[] = [
                'id' => $p->ulid, 'code' => $p->code, 'name' => $p->name, 'stage' => $p->stage->label(), 'stageNumber' => $p->stage->position(),
                'status' => $p->status->value,
                'budget' => round($revised, 2), 'spent' => round($spent, 2), 'used' => $revised > 0 ? round($spent / $revised * 100, 1) : null,
                'paid' => round((float) SupplierInvoice::query()->where('project_id', $p->id)->where('status', 'paid')->sum('subtotal'), 2),
                'highRisks' => Risk::query()->where('project_id', $p->id)->where('status', '!=', 'closed')->whereRaw('likelihood * impact >= 10')->count(),
                'openIncidents' => SafetyIncident::query()->where('project_id', $p->id)->where('status', '!=', 'closed')->count(),
                'openSnags' => Snag::query()->where('project_id', $p->id)->where('status', 'open')->count(),
            ];
        }

        $lastLti = SafetyIncident::query()->whereIn('type', [IncidentType::LostTime->value, IncidentType::Fatality->value])->max('occurred_at');

        return [
            'projects' => $rows,
            'totals' => [
                'projects' => count($rows),
                'budget' => round(array_sum(array_column($rows, 'budget')), 2),
                'spent' => round(array_sum(array_column($rows, 'spent')), 2),
                'paid' => round(array_sum(array_column($rows, 'paid')), 2),
                'highRisks' => array_sum(array_column($rows, 'highRisks')),
                'openIncidents' => array_sum(array_column($rows, 'openIncidents')),
                'pendingApprovals' => ApprovalRequest::query()->where('status', 'pending')->count(),
                'owedToSuppliers' => round((float) SupplierInvoice::query()->whereIn('status', ['approved', 'scheduled'])->sum('total'), 2),
                'daysSinceLti' => $lastLti ? (int) Carbon::parse((string) $lastLti)->diffInDays(now()) : null,
            ],
        ];
    }

    /**
     * Group view for the Super Admin: each active company's totals, computed inside that company's context.
     *
     * @return list<array<string, mixed>>
     */
    public function forGroup(): array
    {
        return Company::query()->where('status', 'active')->orderBy('name')->get()
            ->map(fn (Company $c): array => ['id' => $c->ulid, 'name' => $c->name, ...$this->context->runFor($c, fn (): array => $this->forCurrentCompany()['totals'])])
            ->values()->all();
    }
}
