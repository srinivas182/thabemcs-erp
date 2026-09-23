<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Models\CustomReport;
use App\Domains\Reporting\Reports\CommitmentsReport;
use App\Domains\Reporting\Reports\CostReport;
use App\Domains\Reporting\Reports\CustomReportAdapter;
use App\Domains\Reporting\Reports\LeaveRegisterReport;
use App\Domains\Reporting\Reports\PlantUtilisationReport;
use App\Domains\Reporting\Reports\ProgrammeStatusReport;
use App\Domains\Reporting\Reports\RentalArrearsReport;
use App\Domains\Reporting\Reports\RentRollReport;
use App\Domains\Reporting\Reports\RetentionScheduleReport;
use App\Domains\Reporting\Reports\SafetyStatisticsReport;
use App\Domains\Reporting\Reports\SalesReport;
use App\Domains\Reporting\Reports\SupplierAgeAnalysisReport;
use App\Domains\Reporting\Reports\SupplierComplianceReport;
use App\Domains\Reporting\Reports\VariationRegisterReport;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\Container\Container;

final class ReportRegistry
{
    /** @var list<class-string<Report>> */
    private const array REPORTS = [
        CostReport::class, CommitmentsReport::class, SupplierAgeAnalysisReport::class, RetentionScheduleReport::class,
        SafetyStatisticsReport::class, LeaveRegisterReport::class, PlantUtilisationReport::class,
        VariationRegisterReport::class, ProgrammeStatusReport::class, SupplierComplianceReport::class, SalesReport::class, RentRollReport::class, RentalArrearsReport::class,
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * Standard reports followed by the company's saved custom reports.
     *
     * @return list<Report>
     */
    public function all(): array
    {
        $standard = array_map(fn (string $class): Report => $this->container->make($class), self::REPORTS);
        if ($this->container->make(CurrentCompany::class)->get() === null) {
            return $standard;
        }

        $custom = CustomReport::query()->with('creator:id,name')->orderBy('name')->get()
            ->map(fn (CustomReport $r): Report => new CustomReportAdapter($r, $this->container->make(DatasetRegistry::class)))->all();

        return [...$standard, ...array_values($custom)];
    }

    public function find(string $key): ?Report
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * @return array{content: string, mime: string, extension: string}
     */
    public function export(ReportResult $result, string $format): array
    {
        if ($format !== 'csv') {
            return ['content' => (new XlsxWriter)->write($result), 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extension' => 'xlsx'];
        }

        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return ['content' => '', 'mime' => 'text/csv', 'extension' => 'csv'];
        }
        fputcsv($out, [$result->title]);
        fputcsv($out, [$result->subtitle]);
        fputcsv($out, array_column($result->columns, 'label'));
        foreach ([...$result->rows, ...($result->totals !== null ? [$result->totals] : [])] as $row) {
            fputcsv($out, array_map(static fn (array $c): string => (string) ($row[$c['key']] ?? ''), $result->columns));
        }
        rewind($out);

        return ['content' => (string) stream_get_contents($out), 'mime' => 'text/csv', 'extension' => 'csv'];
    }
}
