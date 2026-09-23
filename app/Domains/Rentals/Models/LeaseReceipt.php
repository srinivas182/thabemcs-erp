<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Money received from a tenant, allocated to an invoice where it is known.
 *
 * @property int $id
 * @property int $lease_id
 * @property int|null $lease_invoice_id
 * @property string $amount
 * @property Carbon $received_on
 * @property string $method
 * @property string|null $reference
 * @property int $captured_by
 */
class LeaseReceipt extends Model
{
    use BelongsToCompany;

    protected $fillable = ['lease_id', 'lease_invoice_id', 'amount', 'received_on', 'method', 'reference', 'captured_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_on' => 'date'];
    }
}
