<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Models\CustomReport;
use App\Domains\Reporting\Services\DatasetRegistry;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

/**
 * Runs a report built in the report designer, so it can be viewed, printed, exported and scheduled
 * exactly like a standard report.
 */
final class CustomReportAdapter implements Report
{
    public function __construct(private readonly CustomReport $report, private readonly DatasetRegistry $datasets) {}

    public function key(): string
    {
        return 'custom-'.$this->report->id;
    }

    public function title(): string
    {
        return $this->report->name;
    }

    public function description(): string
    {
        $label = $this->datasets->definitions()[$this->report->dataset]['label'] ?? $this->report->dataset;

        return "Custom report on {$label}, by {$this->report->creator->name}.";
    }

    public function gate(): string
    {
        return $this->datasets->definitions()[$this->report->dataset]['gate'] ?? 'manage-report-schedules';
    }

    public function filters(): array
    {
        return $this->datasets->hasProjectFilter($this->report->dataset) ? ['project'] : [];
    }

    public function model(): CustomReport
    {
        return $this->report;
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $definition = $this->datasets->definitions()[$this->report->dataset] ?? ['fields' => []];
        $fields = $definition['fields'];
        $config = $this->report->config;
        $columns = array_values(array_filter($config['columns'], static fn (string $c): bool => isset($fields[$c])));

        $result = $this->datasets->rows(
            $this->report->dataset, $filters->project?->id, $config['filters'] ?? [],
            $config['sort'] ?? null, $config['direction'] ?? 'asc',
        );
        $rows = $result['rows'];

        // Conditions on figures worked out in PHP (revised budget, supplier compliance) run on the rows fetched.
        foreach ($result['inPhp'] as $f) {
            $type = $fields[$f['field']]['type'] ?? 'text';
            $rows = array_values(array_filter($rows, static fn (array $r): bool => self::matches($r[$f['field']] ?? null, $f['op'], $f['value'], $type)));
        }

        $rows = array_map(static fn (array $r): array => array_intersect_key($r, array_flip($columns)), $rows);
        $moneyColumns = array_values(array_filter($columns, static fn (string $c): bool => in_array($fields[$c]['type'], ['money', 'number'], true)));
        $totals = ($config['totals'] ?? true) && $moneyColumns !== [] && $columns !== []
            ? [$columns[0] => 'Total', ...ReportResult::sum($rows, $moneyColumns)]
            : null;

        $note = count($rows).' rows.';
        if ($result['truncated']) {
            $note .= ' Showing the first '.count($result['rows'])." of {$result['total']} matching rows; add a condition or filter by project to narrow it.";
        }

        return new ReportResult(
            $this->report->name,
            trim(($config['subtitle'] ?? '').' '.$filters->describe(false, false)),
            array_map(static fn (string $c): array => ['key' => $c, 'label' => $fields[$c]['label'], 'type' => $fields[$c]['type']], $columns),
            $rows,
            $totals,
            $note,
        );
    }

    private static function matches(mixed $value, string $op, string $wanted, string $type): bool
    {
        $numeric = in_array($type, ['money', 'number', 'percent'], true);
        $v = $numeric ? (float) $value : mb_strtolower((string) $value);
        $w = $numeric ? (float) $wanted : mb_strtolower($wanted);

        return match ($op) {
            'eq' => $v === $w,
            'ne' => $v !== $w,
            'contains' => str_contains((string) $v, (string) $w),
            'gte' => $v >= $w,
            'lte' => $v <= $w,
            default => true,
        };
    }
}
