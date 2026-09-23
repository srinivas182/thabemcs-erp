<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Platform\Services\WebhookDispatcher;
use App\Domains\Sales\Models\Buyer;
use App\Domains\Sales\Models\Reservation;
use App\Domains\Sales\Models\SaleAgreement;
use App\Domains\Sales\Models\SaleCondition;
use App\Domains\Sales\Models\SaleUnit;
use App\Domains\Sales\Models\TransferStep;
use App\Domains\Sales\Models\UnitPrice;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Selling units: reservations, agreements, suspensive conditions, transfer and commission.
 *
 * A sale only becomes unconditional once every condition is met or waived, and only registration in the
 * Deeds Office completes it — that is when the unit is transferred and commission becomes payable.
 */
final class SalesService
{
    public function __construct(private readonly ComplianceService $compliance, private readonly WebhookDispatcher $webhooks) {}

    public function changePrice(SaleUnit $unit, float $price, Carbon $from, ?string $reason, User $by): UnitPrice
    {
        return DB::transaction(function () use ($unit, $price, $from, $reason, $by): UnitPrice {
            $record = UnitPrice::query()->create([
                'sale_unit_id' => $unit->id, 'price' => $price, 'effective_from' => $from->toDateString(), 'reason' => $reason, 'created_by' => $by->id,
            ]);
            if (! $from->isFuture()) {
                $unit->update(['list_price' => $price]);
            }

            return $record;
        });
    }

    public function reserve(SaleUnit $unit, Buyer $buyer, Carbon $reservedOn, ?Carbon $expiresOn, float $deposit, ?string $notes, User $by): Reservation
    {
        if ($unit->status !== 'available') {
            throw new SalesException("{$unit->reference} is {$unit->status} and cannot be reserved.");
        }

        return DB::transaction(function () use ($unit, $buyer, $reservedOn, $expiresOn, $deposit, $notes, $by): Reservation {
            $reservation = Reservation::query()->create([
                'sale_unit_id' => $unit->id, 'buyer_id' => $buyer->id, 'reserved_on' => $reservedOn->toDateString(),
                'expires_on' => ($expiresOn ?? $reservedOn->copy()->addDays((int) config('sales.reservation_days', 14)))->toDateString(),
                'deposit_amount' => $deposit, 'notes' => $notes, 'created_by' => $by->id, 'status' => 'active',
            ]);
            $unit->update(['status' => 'reserved']);
            $buyer->update(['status' => 'reserved']);

            return $reservation;
        });
    }

    /**
     * Reservations that have run out put the unit back on the market.
     *
     * @return int reservations released
     */
    public function releaseExpiredReservations(?Carbon $today = null): int
    {
        $today ??= Carbon::today('Africa/Johannesburg');
        $released = 0;

        Reservation::query()->with(['unit', 'buyer'])->where('status', 'active')->whereDate('expires_on', '<', $today->toDateString())
            ->each(function (Reservation $reservation) use (&$released): void {
                DB::transaction(function () use ($reservation): void {
                    $reservation->update(['status' => 'expired']);
                    if ($reservation->unit->status === 'reserved') {
                        $reservation->unit->update(['status' => 'available']);
                    }
                    if ($reservation->buyer->status === 'reserved') {
                        $reservation->buyer->update(['status' => 'qualified']);
                    }
                });
                User::query()->whereKey($reservation->created_by)->first()?->notify(new SystemMessage(
                    "Reservation expired: {$reservation->unit->reference}",
                    "The reservation for {$reservation->buyer->name} ran out on ".$reservation->expires_on->format('j F Y').' and the unit is available again.',
                    route('sales.units', $reservation->unit->project_id),
                ));
                $released++;
            });

        return $released;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{type: string, description?: string|null, due_on?: string|null}>  $conditions
     */
    public function sign(SaleUnit $unit, Buyer $buyer, array $data, array $conditions, User $by): SaleAgreement
    {
        if (! in_array($unit->status, ['available', 'reserved'], true)) {
            throw new SalesException("{$unit->reference} is already {$unit->status}.");
        }

        return DB::transaction(function () use ($unit, $buyer, $data, $conditions, $by): SaleAgreement {
            $signedOn = Carbon::parse((string) $data['signed_on']);
            $price = (float) $data['purchase_price'];
            $percent = $data['commission_percent'] ?? config('sales.commission_percent');

            $agreement = SaleAgreement::query()->create([
                ...$data,
                'number' => (int) SaleAgreement::query()->lockForUpdate()->max('number') + 1,
                'sale_unit_id' => $unit->id, 'buyer_id' => $buyer->id, 'purchase_price' => $price,
                'commission_percent' => $percent,
                // Commission is worked out on the price excluding VAT, as agency mandates normally provide.
                'commission_amount' => round(($data['vat_applies'] ?? true ? $price / 1.15 : $price) * (float) $percent / 100, 2),
                'status' => 'conditional', 'created_by' => $by->id,
            ]);

            /** @var array<string, int> $defaults */
            $defaults = (array) config('sales.condition_days');
            foreach ($conditions as $condition) {
                SaleCondition::query()->create([
                    'sale_agreement_id' => $agreement->id, 'type' => $condition['type'],
                    'description' => $condition['description'] ?? (string) config("sales.condition_types.{$condition['type']}"),
                    'due_on' => $condition['due_on'] ?? $signedOn->copy()->addDays($defaults[$condition['type']] ?? 30)->toDateString(),
                ]);
            }

            $sort = 0;
            foreach ((array) config('sales.transfer_steps') as $step => $label) {
                TransferStep::query()->create(['sale_agreement_id' => $agreement->id, 'step' => $step, 'sort' => $sort++]);
            }

            $unit->update(['status' => 'sold']);
            $buyer->update(['status' => 'purchaser']);
            Reservation::query()->where('sale_unit_id', $unit->id)->where('status', 'active')->update(['status' => 'converted']);

            if ($conditions === []) {
                $agreement->forceFill(['status' => 'unconditional'])->save();
            }

            activity('sales')->causedBy($by)->performedOn($agreement)->log('Sale agreement signed');

            return $agreement->fresh(['conditions', 'steps']) ?? $agreement;
        });
    }

    public function resolveCondition(SaleCondition $condition, string $status, ?Carbon $on, ?string $notes): SaleAgreement
    {
        $condition->update(['status' => $status, 'resolved_on' => ($on ?? Carbon::today())->toDateString(), 'notes' => $notes]);
        $agreement = $condition->getRelationValue('agreement') ?? SaleAgreement::query()->with('conditions')->findOrFail($condition->sale_agreement_id);

        if ($status === 'failed') {
            $this->lapse($agreement, $notes);

            return $agreement;
        }

        $outstanding = $agreement->conditions()->whereIn('status', ['open', 'failed'])->count();
        if ($outstanding === 0 && $agreement->status === 'conditional') {
            $agreement->update(['status' => 'unconditional']);
            activity('sales')->performedOn($agreement)->log('Sale became unconditional');
        }

        return $agreement;
    }

    /** A condition that fails ends the sale and puts the unit back on the market. */
    public function lapse(SaleAgreement $agreement, ?string $reason): void
    {
        DB::transaction(function () use ($agreement, $reason): void {
            $agreement->update(['status' => 'lapsed', 'notes' => trim(($agreement->notes ?? '')."\n".($reason ?? ''))]);
            $agreement->unit->update(['status' => 'available']);
            $agreement->buyer->update(['status' => 'qualified']);
        });
    }

    public function completeStep(TransferStep $step, ?Carbon $on, ?string $notes, User $by): void
    {
        $step->update(['completed_on' => ($on ?? Carbon::today())->toDateString(), 'notes' => $notes]);

        if ($step->step === 'registration') {
            $agreement = SaleAgreement::query()->with('unit')->findOrFail($step->sale_agreement_id);
            if ($agreement->status === 'conditional') {
                throw new SalesException('The sale still has open conditions, so it cannot be registered.');
            }
            DB::transaction(function () use ($agreement, $step, $by): void {
                $agreement->update(['status' => 'registered', 'registered_on' => $step->completed_on?->toDateString()]);
                $agreement->unit->update(['status' => 'transferred']);
                activity('sales')->causedBy($by)->performedOn($agreement)->log('Transfer registered in the Deeds Office');
                $this->webhooks->send('sale.registered', [
                    'agreement' => $agreement->reference(), 'unit' => $agreement->unit->reference,
                    'price' => (float) $agreement->purchase_price, 'registeredOn' => $agreement->registered_on?->toDateString(),
                ]);
            });
        }
    }

    /**
     * Commission may only be approved once the sale is registered, and only to an agency whose Fidelity
     * Fund Certificate is valid (Property Practitioners Act).
     */
    public function approveCommission(SaleAgreement $agreement, User $by): void
    {
        if ($agreement->status !== 'registered') {
            throw new SalesException('Commission is payable once the transfer is registered.');
        }
        $agency = $agreement->agent_supplier_id !== null ? Supplier::query()->find($agreement->agent_supplier_id) : null;
        if ($agency !== null && ! $this->compliance->isCompliant($agency)) {
            throw new SalesException("{$agency->name} may not be paid: their Fidelity Fund Certificate or tax compliance is not in order.");
        }

        $agreement->update(['commission_status' => 'approved']);
        activity('sales')->causedBy($by)->performedOn($agreement)->log('Commission approved for payment');
    }
}
