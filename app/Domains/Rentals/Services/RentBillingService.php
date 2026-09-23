<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Services;

use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Models\LeaseCharge;
use App\Domains\Rentals\Models\LeaseInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly rental billing: one invoice per lease per month, with annual escalations applied on the
 * lease anniversary and any recurring charges added. Billing the same month twice is not possible.
 */
final class RentBillingService
{
    public function __construct(private readonly LeaseService $leases) {}

    /**
     * Bill every active lease for the month containing $month.
     *
     * @return int invoices raised
     */
    public function billMonth(Carbon $month): int
    {
        $raised = 0;
        Lease::query()->with(['charges', 'tenant'])->where('status', 'active')
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->copy()->startOfMonth()->toDateString()))
            ->each(function (Lease $lease) use ($month, &$raised): void {
                if ($this->invoice($lease, $month) !== null) {
                    $raised++;
                }
            });

        return $raised;
    }

    /**
     * Raise one lease's invoice for a month, or return null if it is already billed.
     */
    public function invoice(Lease $lease, Carbon $month): ?LeaseInvoice
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        if (LeaseInvoice::query()->where('lease_id', $lease->id)->whereDate('period_start', $start->toDateString())->exists()) {
            return null;
        }

        $escalations = $lease->escalationsBy($start);
        $rate = (1 + ((float) $lease->escalation_percent / 100)) ** $escalations;

        $lines = [];
        $subtotal = 0.0;
        $vat = 0.0;
        foreach ($lease->charges as $charge) {
            $amount = round((float) $charge->amount * ($charge->escalates ? $rate : 1), 2);
            $lineVat = $charge->vat_applies ? round($amount * 0.15, 2) : 0.0;
            $lines[] = [
                'description' => $charge->type === 'rent' && $escalations > 0
                    ? "{$charge->description} ({$start->format('F Y')}, after {$escalations} escalation".($escalations > 1 ? 's' : '').')'
                    : "{$charge->description} ({$start->format('F Y')})",
                'amount' => $amount, 'vat' => $lineVat,
            ];
            $subtotal += $amount;
            $vat += $lineVat;
        }

        if ($lines === []) {
            return null;
        }

        $dueDay = min($lease->payment_day, (int) $end->format('d'));

        return DB::transaction(function () use ($lease, $start, $end, $lines, $subtotal, $vat, $dueDay): LeaseInvoice {
            return LeaseInvoice::query()->create([
                'number' => (int) LeaseInvoice::query()->lockForUpdate()->max('number') + 1,
                'lease_id' => $lease->id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                'due_on' => $start->copy()->setDay($dueDay)->toDateString(),
                'lines' => $lines, 'subtotal' => round($subtotal, 2), 'vat' => round($vat, 2), 'total' => round($subtotal + $vat, 2),
                'status' => 'issued',
            ]);
        });
    }

    /**
     * Add a charge to a lease from the next invoice onwards (utilities, parking, a levy recovery).
     */
    public function addCharge(Lease $lease, string $type, string $description, float $amount, bool $escalates): LeaseCharge
    {
        return LeaseCharge::query()->create([
            'lease_id' => $lease->id, 'type' => $type, 'description' => $description, 'amount' => $amount,
            'vat_applies' => $lease->vat_applies, 'escalates' => $escalates, 'active' => true,
        ]);
    }

    /**
     * Interest on deposits held in an interest-bearing account, worked out to the end of last month.
     *
     * @return int leases updated
     */
    public function accrueDepositInterest(?Carbon $to = null): int
    {
        $to ??= Carbon::today('Africa/Johannesburg')->startOfMonth()->subDay();
        $rate = (float) config('rentals.deposit_interest_rate', 5.0) / 100;
        $updated = 0;

        Lease::query()->whereIn('status', ['active', 'draft'])->whereNotNull('deposit_received_on')->where('deposit_amount', '>', 0)
            ->each(function (Lease $lease) use ($to, $rate, &$updated): void {
                $from = $lease->deposit_interest_to ?? $lease->deposit_received_on;
                if ($from === null || $from->greaterThanOrEqualTo($to)) {
                    return;
                }
                $days = (int) $from->diffInDays($to);
                $interest = round(((float) $lease->deposit_amount + (float) $lease->deposit_interest) * $rate * $days / 365, 2);
                $lease->update([
                    'deposit_interest' => round((float) $lease->deposit_interest + $interest, 2),
                    'deposit_interest_to' => $to->toDateString(),
                ]);
                $updated++;
            });

        return $updated;
    }

    /**
     * Tell the letting team about leases in arrears, and tenants whose lease is ending soon.
     *
     * @return array{arrears: int, expiring: int}
     */
    public function sendReminders(?Carbon $today = null): array
    {
        $today ??= Carbon::today('Africa/Johannesburg');
        $arrears = 0;
        $expiring = 0;
        $recipients = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales-leasing', 'company-admin', 'director']))->get();

        Lease::query()->with(['tenant', 'unit'])->where('status', 'active')->each(function (Lease $lease) use ($today, $recipients, &$arrears, &$expiring): void {
            $owing = $this->leases->arrears($lease, $today);
            if ($owing['total'] > 0 && $owing['days30'] + $owing['days60'] + $owing['days90'] > 0) {
                $arrears++;
                foreach ($recipients as $person) {
                    $person->notify(new SystemMessage(
                        "Rent in arrears: {$lease->unit->reference}",
                        "{$lease->tenant->name} owes R".number_format($owing['total'], 2, '.', ' ').', oldest due '.$owing['oldestDue'].'.',
                        route('rentals.lease', $lease),
                    ));
                }
            }

            // Leases ending within the notice period need a decision: renew, go month-to-month or end.
            $notice = $lease->notice_days ?? 30;
            if ($lease->ends_on !== null && ! $lease->month_to_month
                && $lease->ends_on->between($today, $today->copy()->addDays($notice))) {
                $expiring++;
                foreach ($recipients as $person) {
                    $person->notify(new SystemMessage(
                        "Lease ending: {$lease->unit->reference}",
                        "{$lease->tenant->name}'s lease ends on ".$lease->ends_on->format('j F Y').'. Renew, move to month-to-month or arrange the outgoing inspection.',
                        route('rentals.lease', $lease),
                    ));
                }
            }
        });

        return ['arrears' => $arrears, 'expiring' => $expiring];
    }
}
