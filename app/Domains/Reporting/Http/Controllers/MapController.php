<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Platform\Models\Company;
use App\Domains\Programme\Services\ScheduleService;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Services\PortfolioService;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Command centre: every live project on a map of South Africa, coloured by health.
 */
final class MapController
{
    public function __construct(private readonly PortfolioService $portfolio, private readonly ScheduleService $schedule, private readonly CurrentCompany $context) {}

    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->context->get() === null) {
            abort_unless($user->is_super_admin, 403);
            $points = [];
            Company::query()->where('status', 'active')->each(function (Company $c) use (&$points): void {
                $points = [...$points, ...$this->context->runFor($c, fn (): array => $this->points($c->name))];
            });
        } else {
            Gate::authorize('view-financial-reports');
            $points = $this->points(null);
        }

        return Inertia::render('reports/map', ['projects' => $points, 'group' => $this->context->get() === null]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function points(?string $company): array
    {
        $rows = collect($this->portfolio->forCurrentCompany()['projects'])->keyBy('id');
        $points = [];

        foreach (Project::query()->whereIn('status', [ProjectStatus::Active, ProjectStatus::OnHold])->get() as $p) {
            $lat = $p->getAttribute('latitude');
            $lng = $p->getAttribute('longitude');
            $row = $rows->get($p->ulid);
            if ($row === null) {
                continue;
            }
            $plan = $this->schedule->calculate($p);
            $behind = count(array_filter($plan['activities'], static fn (array $a): bool => $a['behind']));
            $late = $p->planned_completion_date !== null && $plan['finish'] !== null && $plan['finish'] > $p->planned_completion_date->toDateString();

            // Red: over budget, open incident or late; amber: 80%+ budget used, high risks or activities behind.
            $health = ($row['used'] !== null && $row['used'] >= 100) || $row['openIncidents'] > 0 || $late ? 'red'
                : (($row['used'] !== null && $row['used'] >= 80) || $row['highRisks'] > 0 || $behind > 0 ? 'amber' : 'green');

            $points[] = [
                ...$row, 'company' => $company, 'town' => $p->town, 'lat' => $lat === null ? null : (float) $lat, 'lng' => $lng === null ? null : (float) $lng,
                'behind' => $behind, 'late' => $late, 'forecastFinish' => $plan['finish'], 'health' => $health,
            ];
        }

        return $points;
    }
}
