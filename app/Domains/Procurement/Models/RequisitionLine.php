<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $requisition_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string $estimated_unit_price
 * @property int $sort
 */
class RequisitionLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['requisition_id', 'description', 'quantity', 'unit', 'estimated_unit_price', 'sort'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'estimated_unit_price' => 'decimal:2'];
    }
}
