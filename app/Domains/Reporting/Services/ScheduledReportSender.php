<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Platform\Models\Company;
use App\Domains\Reporting\Mail\ScheduledReport;
use App\Domains\Reporting\Models\ReportSchedule;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\IteratesCompanies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Daily: send each schedule that falls due today. Recipients who can no longer run the report
 * (left the company, deactivated, or lost the role) are skipped.
 */
final class ScheduledReportSender
{
    use IteratesCompanies;

    public function __construct(private readonly ReportRegistry $registry, private readonly CurrentCompany $context) {}

    public function sendDue(?Carbon $today = null, ?Company $only = null): int
    {
        $today ??= Carbon::today('Africa/Johannesburg');
        $sent = 0;

        $this->companies($only)->each(function (Company $company) use ($today, &$sent): void {
            $this->context->runFor($company, function () use ($company, $today, &$sent): void {
                setPermissionsTeamId($company->getKey());

                foreach (ReportSchedule::query()->get() as $schedule) {
                    $report = $this->registry->find($schedule->report);
                    if ($report === null || ! $schedule->isDueOn($today)) {
                        continue;
                    }

                    // Period reports cover the last complete week or month.
                    $filters = ReportFilters::fromArray([
                        ...($schedule->filters ?? []),
                        'from' => $schedule->frequency === 'weekly' ? $today->copy()->subWeek()->startOfWeek()->toDateString() : $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                        'to' => $schedule->frequency === 'weekly' ? $today->copy()->subWeek()->endOfWeek()->toDateString() : $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                        'as_at' => $today->toDateString(),
                    ]);
                    $result = $report->build($filters);
                    $file = $this->registry->export($result, $schedule->format);

                    $recipients = User::query()->whereIn('id', $schedule->recipients)->where('company_id', $company->getKey())->where('is_active', true)->get()
                        ->filter(static fn (User $u): bool => $u->can($report->gate()));

                    foreach ($recipients as $user) {
                        Mail::to($user->email)->send(new ScheduledReport(
                            $result->title, $result->subtitle, $company->name,
                            sprintf('%s-%s.%s', $report->key(), $today->format('Ymd'), $file['extension']), $file['content'], $file['mime'],
                        ));
                        $sent++;
                    }

                    $schedule->forceFill(['last_sent_at' => now()])->save();
                }
            });
        });

        return $sent;
    }
}
