<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $purchase_order_line_id
 * @property string $quantity
 */
class GoodsReceiptLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['goods_receipt_id', 'purchase_order_line_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }
}
