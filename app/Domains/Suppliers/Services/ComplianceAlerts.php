<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Services;

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Suppliers\Models\SupplierDocument;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;

/**
 * Daily: warn procurement and company admins about supplier documents expiring in 30, 14 and 7 days,
 * and those that expired yesterday.
 */
final class ComplianceAlerts
{
    public function __construct(private readonly CurrentCompany $context) {}

    public function send(): int
    {
        $sent = 0;
        /** @var list<int> $days */
        $days = config('supplier_compliance.alert_days', [30, 14, 7]);
        /** @var array<string, array{label: string}> $definitions */
        $definitions = config('supplier_compliance.documents');

        Company::query()->where('status', 'active')->each(function (Company $company) use ($days, $definitions, &$sent): void {
            $this->context->runFor($company, function () use ($company, $days, $definitions, &$sent): void {
                $dates = array_map(static fn (int $d): string => Carbon::today()->addDays($d)->toDateString(), $days);
                $dates[] = Carbon::yesterday()->toDateString();

                $due = SupplierDocument::query()->with('supplier')
                    ->where(function ($q) use ($dates): void {
                        foreach ($dates as $date) {
                            $q->orWhereDate('expires_on', $date);
                        }
                    })->get();
                if ($due->isEmpty()) {
                    return;
                }

                setPermissionsTeamId($company->getKey());
                $recipients = User::query()->where('company_id', $company->getKey())->where('is_active', true)
                    ->role([Role::Procurement->value, Role::CompanyAdmin->value])->get();

                foreach ($due as $doc) {
                    $daysLeft = (int) Carbon::today()->diffInDays($doc->expires_on, false);
                    $label = $definitions[$doc->type]['label'] ?? $doc->type;
                    $message = new SystemMessage(
                        $daysLeft < 0 ? "{$label} expired: {$doc->supplier->name}" : "{$label} expires in {$daysLeft} days: {$doc->supplier->name}",
                        $daysLeft < 0
                            ? 'This supplier can no longer be appointed or paid until a valid document is uploaded.'
                            : 'Ask the supplier for a renewed document before it expires.',
                        route('suppliers.show', $doc->supplier),
                        $daysLeft < 0 ? 'danger' : 'warning',
                    );

                    foreach ($recipients as $user) {
                        $user->notify($message);
                        $sent++;
                    }
                }
            });
        });

        return $sent;
    }
}
