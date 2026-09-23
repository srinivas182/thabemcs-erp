<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Models;

use App\Domains\Sales\Models\SaleUnit;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A lease over a unit: term, rent and escalation, deposit held in an interest-bearing account,
 * recurring charges, invoices and receipts.
 *
 * @property int $id
 * @property string $ulid
 * @property int $number
 * @property int $sale_unit_id
 * @property int $tenant_id
 * @property string $type residential|commercial
 * @property Carbon|null $signed_on
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $month_to_month
 * @property string $rent_amount
 * @property bool $vat_applies
 * @property string $escalation_percent
 * @property int $payment_day
 * @property int|null $notice_days
 * @property string $deposit_amount
 * @property string|null $deposit_account
 * @property Carbon|null $deposit_received_on
 * @property string $deposit_interest
 * @property Carbon|null $deposit_interest_to
 * @property string $deposit_deductions
 * @property Carbon|null $deposit_refunded_on
 * @property string|null $token_hash
 * @property string $status draft|active|ended|cancelled
 * @property Carbon|null $ended_on
 * @property string|null $end_reason
 * @property string|null $notes
 * @property int $created_by
 * @property-read SaleUnit $unit
 * @property-read Tenant $tenant
 * @property-read Collection<int, LeaseCharge> $charges
 * @property-read Collection<int, LeaseInvoice> $invoices
 */
class Lease extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['number', 'sale_unit_id', 'tenant_id', 'type', 'signed_on', 'starts_on', 'ends_on', 'month_to_month', 'rent_amount', 'vat_applies', 'escalation_percent', 'payment_day', 'notice_days', 'deposit_amount', 'deposit_account', 'deposit_received_on', 'deposit_interest', 'deposit_interest_to', 'deposit_deductions', 'deposit_refunded_on', 'status', 'ended_on', 'end_reason', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['signed_on' => 'date', 'starts_on' => 'date', 'ends_on' => 'date', 'deposit_received_on' => 'date', 'deposit_interest_to' => 'date', 'deposit_refunded_on' => 'date', 'ended_on' => 'date', 'month_to_month' => 'boolean', 'vat_applies' => 'boolean', 'rent_amount' => 'decimal:2', 'escalation_percent' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'deposit_interest' => 'decimal:2', 'deposit_deductions' => 'decimal:2', 'payment_day' => 'integer', 'notice_days' => 'integer', 'number' => 'integer'];
    }

    /**
     * @return BelongsTo<SaleUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(SaleUnit::class, 'sale_unit_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<LeaseCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(LeaseCharge::class)->where('active', true);
    }

    /**
     * @return HasMany<LeaseInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(LeaseInvoice::class)->orderByDesc('period_start');
    }

    public function reference(): string
    {
        return 'L-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }

    /** Years since the lease started, which is how many escalations have been applied. */
    public function escalationsBy(Carbon $date): int
    {
        return max(0, (int) floor($this->starts_on->diffInYears($date)));
    }

    /** Rent for a given month, after annual escalations on the anniversary. */
    public function rentAt(Carbon $date): float
    {
        $rate = 1 + ((float) $this->escalation_percent / 100);

        return round((float) $this->rent_amount * $rate ** $this->escalationsBy($date), 2);
    }
}
