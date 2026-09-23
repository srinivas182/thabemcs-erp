<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Services;

use App\Domains\Rentals\Models\Lease;
use App\Domains\Rentals\Models\LeaseCharge;
use App\Domains\Rentals\Models\LeaseInvoice;
use App\Domains\Rentals\Models\LeaseReceipt;
use App\Domains\Rentals\Models\Tenant;
use App\Domains\Sales\Models\SaleUnit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Leases: starting one, the money owed and received, the deposit, and ending it.
 */
final class LeaseService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{lease: Lease, token: string}
     */
    public function create(SaleUnit $unit, Tenant $tenant, array $data, User $by): array
    {
        if ($unit->status === 'transferred' || $unit->status === 'sold') {
            throw new RentalException("{$unit->reference} has been sold and cannot be let.");
        }
        if (Lease::query()->where('sale_unit_id', $unit->id)->where('status', 'active')->exists()) {
            throw new RentalException("{$unit->reference} already has an active lease.");
        }

        $token = Str::random(48);

        $lease = DB::transaction(function () use ($unit, $tenant, $data, $by, $token): Lease {
            $type = (string) ($data['type'] ?? 'residential');
            $lease = Lease::query()->create([
                ...$data,
                'number' => (int) Lease::query()->lockForUpdate()->max('number') + 1,
                'sale_unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'type' => $type,
                // Residential letting is exempt from VAT; commercial letting carries it.
                'vat_applies' => $type === 'commercial' && ($data['vat_applies'] ?? true),
                'notice_days' => $data['notice_days'] ?? config("rentals.notice_days.{$type}"),
                'status' => 'draft', 'created_by' => $by->id,
            ]);
            // The tenant's link token is never mass-assignable: only its hash is stored.
            $lease->forceFill(['token_hash' => hash('sha256', $token)])->save();

            LeaseCharge::query()->create([
                'lease_id' => $lease->id, 'type' => 'rent', 'description' => 'Monthly rent',
                'amount' => $lease->rent_amount, 'vat_applies' => $lease->vat_applies, 'escalates' => true,
            ]);
            if ($unit->tenure === 'sale') {
                $unit->update(['tenure' => 'both']);
            }

            return $lease;
        });

        return ['lease' => $lease, 'token' => $token];
    }

    public function activate(Lease $lease): void
    {
        if ($lease->status !== 'draft') {
            throw new RentalException('Only a draft lease can be activated.');
        }
        DB::transaction(function () use ($lease): void {
            $lease->update(['status' => 'active']);
            $lease->tenant->update(['status' => 'current']);
            activity('rentals')->performedOn($lease)->log('Lease activated');
        });
    }

    /**
     * End a lease. The deposit, less agreed deductions, is refunded with the interest it earned.
     */
    public function end(Lease $lease, Carbon $on, string $reason, float $deductions, ?Carbon $refundedOn): void
    {
        DB::transaction(function () use ($lease, $on, $reason, $deductions, $refundedOn): void {
            $lease->update([
                'status' => 'ended', 'ended_on' => $on->toDateString(), 'end_reason' => $reason,
                'deposit_deductions' => $deductions, 'deposit_refunded_on' => $refundedOn?->toDateString(),
            ]);
            $lease->tenant->update(['status' => 'former']);
            activity('rentals')->performedOn($lease)->withProperties(['deductions' => $deductions])->log('Lease ended');
        });
    }

    /** What the tenant gets back: deposit plus interest, less agreed deductions. */
    public function depositRefundDue(Lease $lease): float
    {
        return round((float) $lease->deposit_amount + (float) $lease->deposit_interest - (float) $lease->deposit_deductions, 2);
    }

    public function receipt(Lease $lease, float $amount, Carbon $on, string $method, ?string $reference, ?LeaseInvoice $invoice, User $by): LeaseReceipt
    {
        return DB::transaction(function () use ($lease, $amount, $on, $method, $reference, $invoice, $by): LeaseReceipt {
            $receipt = LeaseReceipt::query()->create([
                'lease_id' => $lease->id, 'lease_invoice_id' => $invoice?->id, 'amount' => $amount,
                'received_on' => $on->toDateString(), 'method' => $method, 'reference' => $reference, 'captured_by' => $by->id,
            ]);

            // Unallocated money pays the oldest unpaid invoices first.
            $left = $amount;
            $invoices = $invoice !== null ? collect([$invoice]) : LeaseInvoice::query()->where('lease_id', $lease->id)
                ->whereIn('status', ['issued', 'part_paid'])->orderBy('period_start')->get();
            foreach ($invoices as $target) {
                if ($left <= 0) {
                    break;
                }
                $apply = min($left, $target->outstanding());
                if ($apply <= 0) {
                    continue;
                }
                $paid = round((float) $target->paid + $apply, 2);
                $target->update(['paid' => $paid, 'status' => $paid >= (float) $target->total ? 'paid' : 'part_paid']);
                $left = round($left - $apply, 2);
            }

            return $receipt;
        });
    }

    /**
     * What a lease owes: unpaid invoices grouped by how overdue they are.
     *
     * @return array{total: float, current: float, days30: float, days60: float, days90: float, oldestDue: string|null}
     */
    public function arrears(Lease $lease, ?Carbon $asAt = null): array
    {
        $asAt ??= Carbon::today('Africa/Johannesburg');
        $buckets = ['current' => 0.0, 'days30' => 0.0, 'days60' => 0.0, 'days90' => 0.0];
        $oldest = null;

        foreach (LeaseInvoice::query()->where('lease_id', $lease->id)->whereIn('status', ['issued', 'part_paid'])->orderBy('due_on')->get() as $invoice) {
            $outstanding = $invoice->outstanding();
            if ($outstanding <= 0) {
                continue;
            }
            $oldest ??= $invoice->due_on->toDateString();
            $days = (int) $invoice->due_on->diffInDays($asAt, false);
            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => 'days30',
                $days <= 60 => 'days60',
                default => 'days90',
            };
            $buckets[$bucket] += $outstanding;
        }

        return [...array_map(static fn (float $v): float => round($v, 2), $buckets), 'total' => round(array_sum($buckets), 2), 'oldestDue' => $oldest];
    }
}
