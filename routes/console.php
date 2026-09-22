<?php

declare(strict_types=1);

use App\Domains\Compliance\Services\RetentionService;
use App\Domains\Platform\Models\Company;
use App\Domains\Programme\Services\EarnedValueService;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\TaskEscalation;
use App\Domains\Reporting\Services\ScheduledReportSender;
use App\Domains\Suppliers\Services\ComplianceAlerts;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Supplier compliance: warn about documents expiring in 30, 14 and 7 days, and those just expired.
Artisan::command('suppliers:compliance-alerts', function (ComplianceAlerts $alerts): void {
    $this->info('Sent '.$alerts->send().' compliance notifications.');
})->purpose('Notify procurement about expiring supplier documents');

Schedule::command('suppliers:compliance-alerts')->dailyAt('07:00')->timezone('Africa/Johannesburg');

// Approvals waiting longer than the configured time are escalated to Directors and Company Admins.
Artisan::command('approvals:escalate', function (ApprovalEngine $engine, CurrentCompany $context): void {
    $total = 0;
    Company::query()->where('status', 'active')->each(function (Company $company) use ($engine, $context, &$total): void {
        $total += $context->runFor($company, fn (): int => $engine->escalateOverdue($company->id));
    });
    $this->info("Escalated {$total} overdue approvals.");
})->purpose('Escalate overdue approval steps');

Schedule::command('approvals:escalate')->hourly();

// Scheduled reports go out at 06:00 SAST on their day.
Artisan::command('reports:send-scheduled', function (ScheduledReportSender $sender): void {
    $this->info('Sent '.$sender->sendDue().' scheduled report emails.');
})->purpose('Email scheduled reports that are due today');

Schedule::command('reports:send-scheduled')->dailyAt('06:00')->timezone('Africa/Johannesburg');

// Tasks more than two working days overdue are escalated once to the project manager (07:30 SAST).
Artisan::command('tasks:escalate', function (TaskEscalation $escalation): void {
    $this->info('Escalated '.$escalation->run().' overdue tasks.');
})->purpose('Escalate overdue tasks to project managers');

Schedule::command('tasks:escalate')->weekdays()->at('07:30')->timezone('Africa/Johannesburg');

// Weekly earned-value reading for each live project (history on the S-curve).
Artisan::command('programme:snapshot', function (EarnedValueService $evm, CurrentCompany $context): void {
    $n = 0;
    Company::query()->where('status', 'active')->each(function (Company $company) use ($evm, $context, &$n): void {
        $context->runFor($company, function () use ($evm, &$n): void {
            Project::query()->where('status', 'active')->each(function (Project $p) use ($evm, &$n): void {
                $n += $evm->snapshot($p) !== null ? 1 : 0;
            });
        });
    });
    $this->info("Recorded {$n} progress snapshots.");
})->purpose('Record weekly earned-value snapshots');

Schedule::command('programme:snapshot')->weeklyOn(1, '06:30')->timezone('Africa/Johannesburg');

// Monthly POPIA clean-up of personal records past their retention period.
Artisan::command('popia:retention', function (RetentionService $retention): void {
    $this->info('Retention clean-up run for '.$retention->runAll().' companies.');
})->purpose('Apply POPIA retention rules');

Schedule::command('popia:retention')->monthlyOn(1, '02:00')->timezone('Africa/Johannesburg');
