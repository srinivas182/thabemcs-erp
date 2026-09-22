<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\Site\Models\Delivery;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Goods received note (GRN) against a purchase order, optionally linked to a site delivery.
 *
 * @property int $id
 * @property string $ulid
 * @property int $purchase_order_id
 * @property int|null $delivery_id
 * @property string|null $client_id
 * @property string|null $photo_path
 * @property int $number
 * @property Carbon $received_on
 * @property string|null $notes
 * @property int $received_by
 * @property-read User $receiver
 * @property-read Delivery|null $delivery
 * @property-read Collection<int, GoodsReceiptLine> $lines
 */
class GoodsReceipt extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['purchase_order_id', 'delivery_id', 'client_id', 'photo_path', 'number', 'received_on', 'notes', 'received_by'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'number' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<Delivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
