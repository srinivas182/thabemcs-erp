<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Reports;

use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;
use App\Domains\Workforce\Models\LeaveRequest;

final class LeaveRegisterReport implements Report
{
    public function key(): string
    {
        return 'leave-register';
    }

    public function title(): string
    {
        return 'Leave register';
    }

    public function description(): string
    {
        return 'Leave taken or requested in the period, per employee (BCEA record).';
    }

    public function gate(): string
    {
        return 'manage-workforce';
    }

    public function filters(): array
    {
        return ['period'];
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $labels = collect((array) config('workforce.leave'))->map(static fn (array $r): string => (string) $r['label']);
        $rows = LeaveRequest::query()->with('employee')
            ->whereDate('from_date', '<=', $filters->to->toDateString())->whereDate('to_date', '>=', $filters->from->toDateString())
            ->whereIn('status', ['approved', 'pending'])->orderBy('from_date')->get()
            ->map(static fn (LeaveRequest $l): array => [
                'number' => $l->employee->employee_number, 'employee' => $l->employee->name(), 'type' => $labels[$l->type] ?? $l->type,
                'from' => $l->from_date->toDateString(), 'to' => $l->to_date->toDateString(), 'days' => (float) $l->days, 'status' => $l->status,
            ])->values()->all();

        return new ReportResult($this->title(), $filters->describe(true, false), [
            ['key' => 'number', 'label' => 'Employee no.', 'type' => 'text'], ['key' => 'employee', 'label' => 'Employee', 'type' => 'text'],
            ['key' => 'type', 'label' => 'Leave', 'type' => 'text'], ['key' => 'from', 'label' => 'From', 'type' => 'date'],
            ['key' => 'to', 'label' => 'To', 'type' => 'date'], ['key' => 'days', 'label' => 'Working days', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
        ], $rows, ['number' => 'Total', ...ReportResult::sum($rows, ['days'])]);
    }
}
