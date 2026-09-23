<?php

declare(strict_types=1);

use App\Domains\Platform\Jobs\RunCompanyMaintenance;
use App\Domains\Platform\Models\Company;
use App\Domains\Programme\Jobs\SnapshotProjectProgress;
use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Jobs\RefreshProjectMetrics;
use App\Domains\Sales\Services\SalesService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Scheduled work is queued per company (and per project where the work is per project), so it runs in
| parallel across the workers and one large company never holds up the others.
*/

// Supplier compliance: warn about documents expiring in 30, 14 and 7 days, and those just expired.
Artisan::command('suppliers:compliance-alerts', function (): void {
    $this->info('Queued compliance alerts for '.RunCompanyMaintenance::fanOut('compliance-alerts').' companies.');
})->purpose('Notify procurement about expiring supplier documents');

Schedule::command('suppliers:compliance-alerts')->dailyAt('07:00')->timezone('Africa/Johannesburg');

// Approvals waiting longer than the configured time are escalated to Directors and Company Admins.
Artisan::command('approvals:escalate', function (): void {
    $this->info('Queued approval escalation for '.RunCompanyMaintenance::fanOut('approvals-escalate').' companies.');
})->purpose('Escalate overdue approval steps');

Schedule::command('approvals:escalate')->hourly();

// Scheduled reports go out at 06:00 SAST on their day.
Artisan::command('reports:send-scheduled', function (): void {
    $this->info('Queued scheduled reports for '.RunCompanyMaintenance::fanOut('reports-send').' companies.');
})->purpose('Email scheduled reports that are due today');

Schedule::command('reports:send-scheduled')->dailyAt('06:00')->timezone('Africa/Johannesburg');

// Tasks more than two working days overdue are escalated once to the project manager (07:30 SAST).
Artisan::command('tasks:escalate', function (): void {
    $this->info('Queued task escalation for '.RunCompanyMaintenance::fanOut('tasks-escalate').' companies.');
})->purpose('Escalate overdue tasks to project managers');

Schedule::command('tasks:escalate')->weekdays()->at('07:30')->timezone('Africa/Johannesburg');

// Weekly earned-value reading for each live project (history on the S-curve).
Artisan::command('programme:snapshot', function (CurrentCompany $context): void {
    $this->info('Queued '.SnapshotProjectProgress::fanOut($context).' progress snapshots.');
})->purpose('Record weekly earned-value snapshots');

Schedule::command('programme:snapshot')->weeklyOn(1, '06:30')->timezone('Africa/Johannesburg');

// Monthly POPIA clean-up of personal records past their retention period.
Artisan::command('popia:retention', function (): void {
    $this->info('Queued retention clean-up for '.RunCompanyMaintenance::fanOut('retention').' companies.');
})->purpose('Apply POPIA retention rules');

Schedule::command('popia:retention')->monthlyOn(1, '02:00')->timezone('Africa/Johannesburg');

// Nightly refresh of project metrics: "behind" and "late" depend on today's date.
Artisan::command('metrics:refresh', function (CurrentCompany $context): void {
    $queued = 0;
    Company::query()->where('status', 'active')->each(function (Company $company) use ($context, &$queued): void {
        $context->runFor($company, function () use ($company, &$queued): void {
            Project::query()->whereIn('status', ['active', 'on_hold'])->select(['id'])->chunkById(500, function ($projects) use ($company, &$queued): void {
                foreach ($projects as $project) {
                    RefreshProjectMetrics::dispatch((int) $company->getKey(), (int) $project->id);
                    $queued++;
                }
            });
        });
    });
    $this->info("Queued {$queued} project metric refreshes.");
})->purpose('Refresh precomputed project metrics');

Schedule::command('metrics:refresh')->dailyAt('04:30')->timezone('Africa/Johannesburg');

// Reservations that have run out put the unit back on the market (07:00 SAST).
Artisan::command('sales:release-reservations', function (SalesService $sales, CurrentCompany $context): void {
    $released = 0;
    Company::query()->where('status', 'active')->each(function (Company $company) use ($sales, $context, &$released): void {
        $released += $context->runFor($company, fn (): int => $sales->releaseExpiredReservations());
    });
    $this->info("Released {$released} expired reservations.");
})->purpose('Release reservations that have expired');

Schedule::command('sales:release-reservations')->dailyAt('07:00')->timezone('Africa/Johannesburg');

// Rent for the coming month is invoiced a week ahead, with escalations applied on the anniversary.
Artisan::command('rentals:bill', function (): void {
    $this->info('Queued rental billing for '.RunCompanyMaintenance::fanOut('rent-billing').' companies.');
})->purpose('Raise monthly rental invoices');

Schedule::command('rentals:bill')->monthlyOn(24, '05:00')->timezone('Africa/Johannesburg');

// Arrears and lease-expiry reminders to the letting team.
Artisan::command('rentals:reminders', function (): void {
    $this->info('Queued rental reminders for '.RunCompanyMaintenance::fanOut('rent-reminders').' companies.');
})->purpose('Warn about rent arrears and leases ending');

Schedule::command('rentals:reminders')->weekdays()->at('08:00')->timezone('Africa/Johannesburg');
