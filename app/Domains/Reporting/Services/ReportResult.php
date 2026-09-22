<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

/**
 * The table a report produces. Column types drive formatting on screen and in Excel:
 * text, money (ZAR), number, percent, date (Y-m-d).
 */
final class ReportResult
{
    /**
     * @param  list<array{key: string, label: string, type: 'text'|'money'|'number'|'percent'|'date'}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?array $totals = null,
        public readonly ?string $note = null,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, float>
     */
    public static function sum(array $rows, array $keys): array
    {
        $totals = [];
        foreach ($keys as $key) {
            $totals[$key] = round(array_sum(array_map(static fn (array $r): float => is_numeric($r[$key] ?? null) ? (float) $r[$key] : 0.0, $rows)), 2);
        }

        return $totals;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'subtitle' => $this->subtitle, 'columns' => $this->columns, 'rows' => $this->rows, 'totals' => $this->totals, 'note' => $this->note];
    }
}
