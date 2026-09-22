<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Services;

use App\Domains\Compliance\Models\RetentionRule;
use App\Domains\Platform\Models\Company;
use App\Domains\Procurement\Models\RfqInvitation;
use App\Domains\Site\Models\SiteAttendance;
use App\Domains\Workforce\Models\CrewAttendance;
use App\Domains\Workforce\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Monthly POPIA clean-up: personal records older than the company's retention period are deleted
 * or, for former employees, anonymised so that totals and history still add up.
 */
final class RetentionService
{
    public function __construct(private readonly CurrentCompany $context) {}

    /**
     * Rules for the current company, creating the defaults the first time.
     *
     * @return array<string, RetentionRule>
     */
    public function rules(): array
    {
        /** @var array<string, array{label: string, default_months: int, minimum_months: int}> $defaults */
        $defaults = config('popia.retention');
        $rules = [];
        foreach ($defaults as $record => $d) {
            $rules[$record] = RetentionRule::query()->firstOrCreate(['record' => $record], ['keep_months' => $d['default_months']]);
        }

        return $rules;
    }

    /**
     * @return array<string, int> records affected per rule
     */
    public function runForCurrentCompany(?Carbon $today = null): array
    {
        $today ??= Carbon::today('Africa/Johannesburg');
        $company = $this->context->require();
        $done = [];

        foreach ($this->rules() as $record => $rule) {
            $cutoff = $today->copy()->subMonthsNoOverflow($rule->keep_months);
            $count = match ($record) {
                'attendance_selfies' => $this->selfies($cutoff),
                'crew_attendance' => CrewAttendance::query()->whereDate('worked_on', '<', $cutoff->toDateString())->delete(),
                'former_employees' => $this->anonymiseFormerEmployees($cutoff),
                'read_notifications' => $this->notifications($company, $cutoff),
                'rfq_links' => RfqInvitation::query()->whereDate('closes_on', '<', $cutoff->toDateString())->delete(),
                default => 0,
            };
            $rule->forceFill(['last_run_at' => now(), 'last_run_count' => $count])->save();
            $done[$record] = $count;
        }

        activity('popia')->withProperties($done)->log('Retention clean-up run');

        return $done;
    }

    /**
     * @return int companies processed
     */
    public function runAll(): int
    {
        $n = 0;
        Company::query()->where('status', 'active')->each(function (Company $c) use (&$n): void {
            $this->context->runFor($c, fn () => $this->runForCurrentCompany());
            $n++;
        });

        return $n;
    }

    private function selfies(Carbon $cutoff): int
    {
        $count = 0;
        SiteAttendance::query()->whereNotNull('selfie_path')->where('captured_at', '<', $cutoff)->each(function (SiteAttendance $a) use (&$count): void {
            Storage::disk((string) config('platform.documents_disk'))->delete((string) $a->selfie_path);
            $a->forceFill(['selfie_path' => null])->save();
            $count++;
        });

        return $count;
    }

    private function anonymiseFormerEmployees(Carbon $cutoff): int
    {
        $count = 0;
        Employee::query()->where('status', 'left')->whereNotNull('end_date')->whereDate('end_date', '<', $cutoff->toDateString())
            ->where('first_name', '!=', 'Former')
            ->each(function (Employee $e) use (&$count): void {
                $e->forceFill([
                    'first_name' => 'Former', 'last_name' => "employee {$e->employee_number}", 'id_number' => null,
                    'phone' => null, 'emergency_contact' => null, 'user_id' => null,
                ])->saveQuietly();
                $count++;
            });

        return $count;
    }

    private function notifications(Company $company, Carbon $cutoff): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', User::query()->where('company_id', $company->getKey())->select('id'))
            ->whereNotNull('read_at')->where('read_at', '<', $cutoff)
            ->delete();
    }
}
