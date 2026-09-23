<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * A recurring line on the monthly invoice: rent, utilities, parking or another charge.
 *
 * @property int $id
 * @property int $lease_id
 * @property string $type
 * @property string $description
 * @property string $amount
 * @property bool $vat_applies
 * @property bool $escalates
 * @property bool $active
 */
class LeaseCharge extends Model
{
    use BelongsToCompany;

    protected $fillable = ['lease_id', 'type', 'description', 'amount', 'vat_applies', 'escalates', 'active'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'vat_applies' => 'boolean', 'escalates' => 'boolean', 'active' => 'boolean'];
    }
}
