<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
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
