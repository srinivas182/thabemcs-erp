<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\Suppliers\Models\Supplier;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A signed deed of sale: price, deposit, bond, suspensive conditions, transfer and commission.
 *
 * @property int $id
 * @property string $ulid
 * @property int $number
 * @property int $sale_unit_id
 * @property int $buyer_id
 * @property int|null $agent_supplier_id
 * @property int|null $agent_user_id
 * @property int|null $conveyancer_supplier_id
 * @property Carbon $signed_on
 * @property string $purchase_price
 * @property bool $vat_applies
 * @property string $deposit_amount
 * @property Carbon|null $deposit_due_on
 * @property Carbon|null $deposit_received_on
 * @property string|null $deposit_held_by
 * @property string|null $trust_account_ref
 * @property bool $bond_required
 * @property string|null $bond_amount
 * @property string|null $bond_originator
 * @property Carbon|null $occupation_date
 * @property string|null $commission_percent
 * @property string|null $commission_amount
 * @property string $commission_status pending|approved|paid
 * @property string $status conditional|unconditional|registered|lapsed|cancelled
 * @property Carbon|null $registered_on
 * @property string|null $notes
 * @property int $created_by
 * @property-read SaleUnit $unit
 * @property-read Buyer $buyer
 * @property-read Supplier|null $agency
 * @property-read Supplier|null $conveyancer
 * @property-read Collection<int, SaleCondition> $conditions
 * @property-read Collection<int, TransferStep> $steps
 */
class SaleAgreement extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['number', 'sale_unit_id', 'buyer_id', 'agent_supplier_id', 'agent_user_id', 'conveyancer_supplier_id', 'signed_on', 'purchase_price', 'vat_applies', 'deposit_amount', 'deposit_due_on', 'deposit_received_on', 'deposit_held_by', 'trust_account_ref', 'bond_required', 'bond_amount', 'bond_originator', 'occupation_date', 'commission_percent', 'commission_amount', 'commission_status', 'status', 'registered_on', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['signed_on' => 'date', 'deposit_due_on' => 'date', 'deposit_received_on' => 'date', 'occupation_date' => 'date', 'registered_on' => 'date', 'purchase_price' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'bond_amount' => 'decimal:2', 'commission_percent' => 'decimal:2', 'commission_amount' => 'decimal:2', 'vat_applies' => 'boolean', 'bond_required' => 'boolean', 'number' => 'integer'];
    }

    /**
     * @return BelongsTo<SaleUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(SaleUnit::class, 'sale_unit_id');
    }

    /**
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'agent_supplier_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function conveyancer(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'conveyancer_supplier_id');
    }

    /**
     * @return HasMany<SaleCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(SaleCondition::class);
    }

    /**
     * @return HasMany<TransferStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TransferStep::class)->orderBy('sort');
    }

    public function reference(): string
    {
        return 'SA-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }

    /** Price excluding VAT: what reaches the books, the feasibility and profitability. */
    public function netPrice(): float
    {
        return $this->vat_applies ? round((float) $this->purchase_price / 1.15, 2) : (float) $this->purchase_price;
    }
}
