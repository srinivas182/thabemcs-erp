<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Models;

use App\Domains\Sales\Models\SaleUnit;
use App\Domains\Suppliers\Models\Supplier;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something needing repair at a unit, reported by staff or by the tenant through their link.
 *
 * @property int $id
 * @property string $ulid
 * @property int $number
 * @property int|null $lease_id
 * @property int $sale_unit_id
 * @property string $category
 * @property string $description
 * @property string $priority urgent|normal|low
 * @property string $status new|assigned|in_progress|completed|cancelled
 * @property bool $reported_by_tenant
 * @property int|null $supplier_id
 * @property string|null $cost
 * @property bool $recover_from_tenant
 * @property Carbon $reported_on
 * @property Carbon|null $completed_on
 * @property string|null $resolution
 * @property-read SaleUnit $unit
 * @property-read Supplier|null $supplier
 * @property-read Lease|null $lease
 */
class MaintenanceRequest extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['number', 'lease_id', 'sale_unit_id', 'category', 'description', 'priority', 'status', 'reported_by_tenant', 'supplier_id', 'cost', 'recover_from_tenant', 'reported_on', 'completed_on', 'resolution'];

    protected function casts(): array
    {
        return ['reported_on' => 'date', 'completed_on' => 'date', 'cost' => 'decimal:2', 'reported_by_tenant' => 'boolean', 'recover_from_tenant' => 'boolean', 'number' => 'integer'];
    }

    /**
     * @return BelongsTo<SaleUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(SaleUnit::class, 'sale_unit_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return 'MR-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }
}
