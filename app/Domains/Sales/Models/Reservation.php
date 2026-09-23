<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A unit held for a buyer for a limited time, usually against a reservation deposit.
 *
 * @property int $id
 * @property string $ulid
 * @property int $sale_unit_id
 * @property int $buyer_id
 * @property Carbon $reserved_on
 * @property Carbon $expires_on
 * @property string $deposit_amount
 * @property Carbon|null $deposit_received_on
 * @property string $status active|converted|expired|cancelled
 * @property string|null $notes
 * @property int $created_by
 * @property-read SaleUnit $unit
 * @property-read Buyer $buyer
 */
class Reservation extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['sale_unit_id', 'buyer_id', 'reserved_on', 'expires_on', 'deposit_amount', 'deposit_received_on', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['reserved_on' => 'date', 'expires_on' => 'date', 'deposit_received_on' => 'date', 'deposit_amount' => 'decimal:2'];
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
}
