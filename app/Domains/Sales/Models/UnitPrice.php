<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A price change on a unit, kept as history so escalations can be traced.
 *
 * @property int $id
 * @property int $sale_unit_id
 * @property string $price
 * @property Carbon $effective_from
 * @property string|null $reason
 * @property int $created_by
 */
class UnitPrice extends Model
{
    use BelongsToCompany;

    protected $fillable = ['sale_unit_id', 'price', 'effective_from', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'effective_from' => 'date'];
    }
}
