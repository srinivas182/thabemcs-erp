<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A monthly rental invoice. The lines are stored as they were billed, so history stays readable.
 *
 * @property int $id
 * @property string $ulid
 * @property int $number
 * @property int $lease_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $due_on
 * @property list<array{description: string, amount: float, vat: float}> $lines
 * @property string $subtotal
 * @property string $vat
 * @property string $total
 * @property string $paid
 * @property string $status issued|part_paid|paid|credited
 * @property-read Lease $lease
 */
class LeaseInvoice extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['number', 'lease_id', 'period_start', 'period_end', 'due_on', 'lines', 'subtotal', 'vat', 'total', 'paid', 'status'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'due_on' => 'date', 'lines' => 'array', 'subtotal' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2', 'paid' => 'decimal:2', 'number' => 'integer'];
    }

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function reference(): string
    {
        return 'RI-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    public function outstanding(): float
    {
        return round((float) $this->total - (float) $this->paid, 2);
    }
}
