<?php

declare(strict_types=1);

namespace App\Domains\Platform\Console;

use App\Domains\Finance\Services\ProfitabilityService;
use App\Domains\Platform\Models\Company;
use App\Domains\Programme\Services\ScheduleService;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Services\PortfolioService;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportRegistry;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Times the work behind the heaviest pages and counts the queries each makes, against whatever data is
 * in the database. Run it before and after a load test, and after any change to a dashboard or report.
 */
final class MeasurePerformanceCommand extends Command
{
    protected $signature = 'perf:measure {--company= : Company id to measure (defaults to the first active one)}';

    protected $description = 'Time the dashboard, command centre, reports and project pages';

    public function handle(CurrentCompany $context, PortfolioService $portfolio, ReportRegistry $reports, ProfitabilityService $profitability, ScheduleService $schedule): int
    {
        $company = $this->option('company') !== null
            ? Company::query()->findOrFail((int) $this->option('company'))
            : Company::query()->where('status', 'active')->firstOrFail();

        $this->info("Measuring against {$company->name}.");
        $this->line('Projects in this company: '.number_format(Project::query()->withoutGlobalScopes()->where('company_id', $company->getKey())->count()));

        $results = $context->runFor($company, function () use ($portfolio, $reports, $profitability, $schedule): array {
            $project = Project::query()->where('status', 'active')->first();
            $measurements = [
                'Portfolio dashboard' => fn () => $portfolio->forCurrentCompany(),
                'Attention list (100)' => fn () => $portfolio->attentionList(100, true),
                'Cost report' => fn () => $reports->find('cost-report')?->build(ReportFilters::fromArray([])),
                'Supplier age analysis' => fn () => $reports->find('supplier-age-analysis')?->build(ReportFilters::fromArray([])),
                'Programme status' => fn () => $reports->find('programme-status')?->build(ReportFilters::fromArray([])),
            ];
            if ($project !== null) {
                $measurements['Project profitability'] = fn () => $profitability->forProject($project);
                $measurements['Critical path (one project)'] = fn () => $schedule->calculate($project);
            }

            $rows = [];
            foreach ($measurements as $name => $work) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $started = microtime(true);
                $work();
                $ms = round((microtime(true) - $started) * 1000, 1);
                $queries = count(DB::getQueryLog());
                DB::disableQueryLog();
                $rows[] = [$name, $ms.' ms', $queries, $ms > 500 ? 'SLOW' : 'ok'];
            }

            return $rows;
        });

        $this->table(['What', 'Time', 'Queries', ''], $results);

        $slow = array_filter($results, static fn (array $row): bool => $row[3] === 'SLOW');
        if ($slow !== []) {
            $this->warn(count($slow).' measurements are over 500 ms. Record them in docs/performance.md and look at the query log.');
        }

        return self::SUCCESS;
    }
}
