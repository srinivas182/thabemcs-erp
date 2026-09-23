<?php

declare(strict_types=1);

namespace App\Domains\Platform\Jobs;

use App\Domains\Compliance\Services\RetentionService;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Services\TaskEscalation;
use App\Domains\Rentals\Services\RentBillingService;
use App\Domains\Reporting\Services\ScheduledReportSender;
use App\Domains\Suppliers\Services\ComplianceAlerts;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use InvalidArgumentException;

/**
 * Scheduled work for one company. The scheduler queues one job per company rather than working
 * through every company in a single process, so nightly work runs in parallel across the workers
 * and one slow company never holds up the rest.
 */
final class RunCompanyMaintenance implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const array TASKS = ['compliance-alerts', 'approvals-escalate', 'reports-send', 'tasks-escalate', 'retention', 'rent-billing', 'rent-reminders', 'digest'];

    public int $uniqueFor = 3600;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public readonly int $companyId, public readonly string $task)
    {
        if (! in_array($task, self::TASKS, true)) {
            throw new InvalidArgumentException("Unknown maintenance task {$task}.");
        }
        $this->onQueue($task === 'reports-send' ? 'reports' : 'maintenance');
    }

    public function uniqueId(): string
    {
        return "{$this->task}:{$this->companyId}";
    }

    public function handle(Container $container, CurrentCompany $context): void
    {
        $company = Company::query()->find($this->companyId);
        if ($company === null || $company->status !== 'active') {
            return;
        }

        $context->runFor($company, function () use ($container, $company): void {
            match ($this->task) {
                'compliance-alerts' => $container->make(ComplianceAlerts::class)->send($company),
                'approvals-escalate' => $container->make(ApprovalEngine::class)->escalateOverdue((int) $company->getKey()),
                'reports-send' => $container->make(ScheduledReportSender::class)->sendDue(null, $company),
                'tasks-escalate' => $container->make(TaskEscalation::class)->run($company),
                'retention' => $container->make(RetentionService::class)->runForCurrentCompany(),
                'rent-billing' => $this->bill($container),
                'rent-reminders' => $container->make(RentBillingService::class)->sendReminders(),
                'digest' => SendNotificationDigest::dispatch($this->companyId),
                default => null,
            };
        });
    }

    /**
     * Rent for next month is invoiced a few days ahead, and the deposit interest is brought up to date.
     */
    private function bill(Container $container): void
    {
        $billing = $container->make(RentBillingService::class);
        $billing->accrueDepositInterest();
        $billing->billMonth(now('Africa/Johannesburg')->addDays((int) config('rentals.bill_days_ahead', 7)));
    }

    /**
     * Queue this task for every active company.
     */
    public static function fanOut(string $task): int
    {
        $queued = 0;
        Company::query()->where('status', 'active')->select(['id'])->chunkById(500, function ($companies) use ($task, &$queued): void {
            foreach ($companies as $company) {
                self::dispatch((int) $company->id, $task);
                $queued++;
            }
        });

        return $queued;
    }
}
