<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Services;

use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\LeaveRequest;
use App\Domains\Workforce\Models\OvertimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Leave balances and overtime limits under the Basic Conditions of Employment Act.
 */
final class WorkforceService
{
    public function __construct(private readonly PublicHolidays $holidays) {}

    /**
     * Leave entitlement and days taken in the current cycle (annual and family: per leave year from the start date;
     * sick: per 3-year cycle from the start date).
     *
     * @return array<string, array{label: string, entitlement: float|null, taken: float, remaining: float|null}>
     */
    public function balances(Employee $employee, ?Carbon $on = null): array
    {
        $on ??= Carbon::today();
        $scale = $employee->days_per_week / 5;
        $yearsIn = (int) $employee->start_date->diffInYears($on);
        $yearStart = $employee->start_date->copy()->addYears($yearsIn);
        $cycleStart = $employee->start_date->copy()->addYears(intdiv($yearsIn, 3) * 3);

        $result = [];
        /** @var array<string, array{label: string, days_per_year?: int, days_per_cycle?: int}> $types */
        $types = config('workforce.leave');
        foreach ($types as $type => $rule) {
            $from = $type === 'sick' ? $cycleStart : $yearStart;
            $taken = (float) LeaveRequest::query()->where('employee_id', $employee->id)->where('type', $type)
                ->whereIn('status', ['approved', 'pending'])->whereDate('from_date', '>=', $from)->sum('days');
            $entitlement = isset($rule['days_per_year']) ? $rule['days_per_year'] * $scale : (isset($rule['days_per_cycle']) ? $rule['days_per_cycle'] * $scale : null);

            $result[$type] = [
                'label' => $rule['label'],
                'entitlement' => $entitlement === null ? null : round($entitlement, 1),
                'taken' => $taken,
                'remaining' => $entitlement === null ? null : round($entitlement - $taken, 1),
            ];
        }

        return $result;
    }

    /**
     * @throws ValidationException
     */
    public function requestLeave(Employee $employee, string $type, Carbon $from, Carbon $to, ?string $notes): LeaveRequest
    {
        $days = $this->holidays->workingDays($from, $to, $employee->days_per_week);
        if ($days === 0) {
            throw ValidationException::withMessages(['from_date' => 'Those dates are all weekends or public holidays.']);
        }

        $balance = $this->balances($employee, $from)[$type] ?? null;
        if ($balance !== null && $balance['remaining'] !== null && $days > $balance['remaining'] && $type !== 'unpaid') {
            throw ValidationException::withMessages(['to_date' => sprintf('Only %s days of %s left in this cycle; %d requested. Record the rest as unpaid leave.', $balance['remaining'], strtolower($balance['label']), $days)]);
        }

        return LeaveRequest::query()->create([
            'employee_id' => $employee->id, 'type' => $type, 'from_date' => $from->toDateString(), 'to_date' => $to->toDateString(),
            'days' => $days, 'status' => 'pending', 'notes' => $notes,
        ]);
    }

    /**
     * BCEA s10: overtime by agreement, at most 3 hours a day and 10 hours a week; 1.5x normally,
     * 2x on Sundays and public holidays.
     *
     * @throws ValidationException
     */
    public function recordOvertime(Employee $employee, Carbon $date, float $hours, ?int $projectId, ?string $reason, User $by): OvertimeEntry
    {
        $maxDay = (float) config('workforce.overtime_max_hours_per_day', 3);
        $maxWeek = (float) config('workforce.overtime_max_hours_per_week', 10);

        $day = (float) OvertimeEntry::query()->where('employee_id', $employee->id)->whereDate('worked_on', $date->toDateString())->sum('hours');
        if ($day + $hours > $maxDay) {
            throw ValidationException::withMessages(['hours' => sprintf('The BCEA allows at most %s hours of overtime a day; %s already recorded for this day.', $maxDay, $day)]);
        }

        $week = (float) OvertimeEntry::query()->where('employee_id', $employee->id)
            ->whereBetween('worked_on', [$date->copy()->startOfWeek()->toDateString(), $date->copy()->endOfWeek()->toDateString()])->sum('hours');
        if ($week + $hours > $maxWeek) {
            throw ValidationException::withMessages(['hours' => sprintf('The BCEA allows at most %s hours of overtime a week; %s already recorded this week.', $maxWeek, $week)]);
        }

        $double = $date->isSunday() || $this->holidays->isHoliday($date);

        return OvertimeEntry::query()->create([
            'employee_id' => $employee->id, 'project_id' => $projectId, 'worked_on' => $date->toDateString(), 'hours' => $hours,
            'rate_multiplier' => $double ? 2.0 : 1.5, 'reason' => $reason, 'status' => 'approved', 'recorded_by' => $by->id,
        ]);
    }
}
