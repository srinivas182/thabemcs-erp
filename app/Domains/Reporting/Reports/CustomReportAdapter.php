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

        $rows = $this->datasets->rows($this->report->dataset, $filters->project?->id);

        foreach ($config['filters'] ?? [] as $f) {
            if (! isset($fields[$f['field']])) {
                continue;
            }
            $type = $fields[$f['field']]['type'];
            $rows = array_values(array_filter($rows, static fn (array $r): bool => self::matches($r[$f['field']] ?? null, $f['op'], $f['value'], $type)));
        }

        if (! empty($config['sort']) && isset($fields[$config['sort']])) {
            $key = $config['sort'];
            $desc = ($config['direction'] ?? 'asc') === 'desc';
            usort($rows, static fn (array $a, array $b): int => ($desc ? -1 : 1) * (($a[$key] ?? '') <=> ($b[$key] ?? '')));
        }

        $rows = array_map(static fn (array $r): array => array_intersect_key($r, array_flip($columns)), $rows);
        $moneyColumns = array_values(array_filter($columns, static fn (string $c): bool => in_array($fields[$c]['type'], ['money', 'number'], true)));
        $totals = ($config['totals'] ?? true) && $moneyColumns !== [] && $columns !== []
            ? [$columns[0] => 'Total', ...ReportResult::sum($rows, $moneyColumns)]
            : null;

        return new ReportResult(
            $this->report->name,
            trim(($config['subtitle'] ?? '').' '.$filters->describe(false, false)),
            array_map(static fn (string $c): array => ['key' => $c, 'label' => $fields[$c]['label'], 'type' => $fields[$c]['type']], $columns),
            $rows,
            $totals,
            count($rows).' rows.',
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
