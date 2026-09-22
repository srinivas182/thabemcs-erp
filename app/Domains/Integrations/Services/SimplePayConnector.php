<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\Integration;
use App\Domains\Workforce\Models\CrewAttendance;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\EmployeeAllowance;
use App\Domains\Workforce\Models\LeaveRequest;
use App\Domains\Workforce\Models\OvertimeEntry;
use App\Domains\Workforce\Services\PublicHolidays;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * SimplePay payroll (https://api.payroll.simplepay.cloud/v1, "Authorization: <api key>" header).
 *
 * Overtime hours and allowances go to the payslip for the period through Bulk Inputs
 * (POST /clients/:client_id/bulk_input, attributes "calc.<item>.<input>"). Approved leave goes to
 * POST /employees/:id/leave_days/create_multiple. Which payslip item receives each value is set per
 * company in the integration settings, because it depends on how their SimplePay account is set up.
 */
final class SimplePayConnector
{
    public const string PROVIDER = 'simplepay';

    public const string BASE = 'https://api.payroll.simplepay.cloud/v1';

    public function __construct(private readonly IntegrationRepository $repo, private readonly PublicHolidays $holidays) {}

    /**
     * @return list<string> client (company) names on the key
     */
    public function test(Integration $integration): array
    {
        $response = $this->http($integration)->get(self::BASE.'/clients');
        if (! $response->successful()) {
            throw new IntegrationException('SimplePay refused the connection ('.$response->status().'). Check the API key.');
        }
        /** @var list<array{client?: array{id?: int, name?: string}}> $clients */
        $clients = $response->json() ?? [];

        return array_values(array_map(static fn (array $c): string => ($c['client']['name'] ?? '').' (ID '.($c['client']['id'] ?? '?').')', $clients));
    }

    /**
     * Payslip inputs (overtime and allowances) for the pay period ending on $to.
     *
     * @return array{sent: int, failed: list<string>, skipped: list<string>}
     */
    public function pushPayslipInputs(Integration $integration, Carbon $from, Carbon $to): array
    {
        $this->assertReady($integration);
        $settings = $integration->settings ?? [];
        /** @var array<string, string> $items */
        $items = (array) ($settings['items'] ?? []);

        $entities = [];
        $skipped = [];
        foreach (Employee::query()->where('status', 'active')->get() as $employee) {
            $attributes = [];

            $overtime = OvertimeEntry::query()->where('employee_id', $employee->id)->where('status', 'approved')
                ->whereBetween('worked_on', [$from->toDateString(), $to->toDateString()])->get()
                ->groupBy(static fn (OvertimeEntry $o): string => (float) $o->rate_multiplier >= 2 ? 'overtime_double' : 'overtime');
            foreach ($overtime as $kind => $entries) {
                $attributes[$kind] = (float) $entries->sum(static fn (OvertimeEntry $o): float => (float) $o->hours);
            }

            foreach (EmployeeAllowance::query()->where('employee_id', $employee->id)->whereDate('from_date', '<=', $to->toDateString())
                ->where(fn ($q) => $q->whereNull('to_date')->orWhereDate('to_date', '>=', $from->toDateString()))->get() as $a) {
                $amount = match ($a->frequency) {
                    'day' => (float) $a->amount * CrewAttendance::query()->where('employee_id', $employee->id)->where('status', 'present')
                        ->whereBetween('worked_on', [$from->toDateString(), $to->toDateString()])->count(),
                    'month' => (float) $a->amount,
                    default => $a->from_date->between($from, $to) ? (float) $a->amount : 0.0,
                };
                if ($amount > 0) {
                    $attributes["allowance_{$a->type}"] = ($attributes["allowance_{$a->type}"] ?? 0) + $amount;
                }
            }

            if ($attributes === []) {
                continue;
            }
            if ($employee->payroll_ref === null) {
                $skipped[] = "{$employee->name()} has no SimplePay employee ID";

                continue;
            }

            $mapped = [];
            foreach ($attributes as $key => $value) {
                if (empty($items[$key])) {
                    $skipped[] = "No SimplePay payslip item is set for {$key}";

                    continue;
                }
                $mapped[(string) $items[$key]] = number_format($value, 2, '.', '');
            }
            if ($mapped !== []) {
                $entities[] = ['id' => (string) $employee->payroll_ref, 'payslip_date' => $to->toDateString(), 'attributes' => $mapped];
            }
        }

        if ($entities === []) {
            return ['sent' => 0, 'failed' => [], 'skipped' => array_values(array_unique($skipped))];
        }

        $response = $this->http($integration)->post(self::BASE.'/clients/'.$settings['client_id'].'/bulk_input', ['entities' => $entities]);
        if (! $response->successful()) {
            throw new IntegrationException('SimplePay did not accept the inputs ('.$response->status().').');
        }

        $sent = 0;
        $failed = [];
        /** @var list<array{id?: string, success?: string|bool, message?: string, errors?: array<string, string>}> $results */
        $results = $response->json() ?? [];
        foreach ($results as $r) {
            if (in_array($r['success'] ?? false, [true, 'true'], true)) {
                $sent++;
            } else {
                $failed[] = ($r['message'] ?? 'Employee '.($r['id'] ?? '?')).(isset($r['errors']) ? ': '.implode('; ', $r['errors']) : '');
            }
        }
        $integration->forceFill(['last_synced_at' => now(), 'last_error' => $failed ? implode(' | ', $failed) : null])->save();

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => array_values(array_unique($skipped))];
    }

    /**
     * Approved leave starting in the period, one leave day per working day.
     *
     * @return array{sent: int, failed: list<string>, skipped: list<string>}
     */
    public function pushLeave(Integration $integration, Carbon $from, Carbon $to): array
    {
        $this->assertReady($integration);
        /** @var array<string, string> $types */
        $types = (array) (($integration->settings ?? [])['leave_types'] ?? []);
        $sent = 0;
        $failed = [];
        $skipped = [];

        LeaveRequest::query()->with('employee')->where('status', 'approved')->whereBetween('from_date', [$from->toDateString(), $to->toDateString()])->get()
            ->each(function (LeaveRequest $leave) use ($integration, $types, &$sent, &$failed, &$skipped): void {
                if ($this->repo->alreadySent(self::PROVIDER, 'leave_request', $leave->id)) {
                    return;
                }
                if ($leave->employee->payroll_ref === null || empty($types[$leave->type])) {
                    $skipped[] = $leave->employee->payroll_ref === null ? "{$leave->employee->name()} has no SimplePay employee ID" : "No SimplePay leave type is set for {$leave->type} leave";

                    return;
                }
                $dates = [];
                for ($d = $leave->from_date->copy(); $d->lessThanOrEqualTo($leave->to_date); $d->addDay()) {
                    $working = $leave->employee->days_per_week >= 6 ? ! $d->isSunday() : $d->isWeekday();
                    if ($working && ! $this->holidays->isHoliday($d)) {
                        $dates[] = ['date' => $d->toDateString(), 'type_id' => (int) $types[$leave->type]];
                    }
                }
                $response = $this->http($integration)->post(self::BASE."/employees/{$leave->employee->payroll_ref}/leave_days/create_multiple", ['dates' => $dates]);
                $ok = $response->successful();
                $this->repo->record(self::PROVIDER, 'leave_request', $leave->id, $ok, null, $ok ? null : "HTTP {$response->status()}: ".mb_substr($response->body(), 0, 500));
                $ok ? $sent++ : $failed[] = "Leave for {$leave->employee->name()} failed ({$response->status()})";
            });

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => array_values(array_unique($skipped))];
    }

    private function assertReady(Integration $integration): void
    {
        if (! $integration->enabled || empty(($integration->credentials ?? [])['api_key']) || empty(($integration->settings ?? [])['client_id'])) {
            throw new IntegrationException('SimplePay is not set up: switch it on and enter the API key and client ID.');
        }
    }

    private function http(Integration $integration): PendingRequest
    {
        return Http::withHeaders(['Authorization' => (string) (($integration->credentials ?? [])['api_key'] ?? '')])->acceptJson()->asJson()->timeout(30);
    }
}
