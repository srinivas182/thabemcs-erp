<?php

declare(strict_types=1);

use App\Domains\Suppliers\Services\ComplianceAlerts;
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
