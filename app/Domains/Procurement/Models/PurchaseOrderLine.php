<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string $unit_price
 * @property string $received_quantity
 * @property int $sort
 */
class PurchaseOrderLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['purchase_order_id', 'description', 'quantity', 'unit', 'unit_price', 'received_quantity', 'sort'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'received_quantity' => 'decimal:3'];
    }

    public function outstanding(): float
    {
        return max(0.0, (float) $this->quantity - (float) $this->received_quantity);
    }
}
