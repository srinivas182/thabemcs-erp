<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Rentals\Models\Lease;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A unit of stock in a development: a serviced erf, house, sectional title unit or commercial unit.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $reference
 * @property string $type
 * @property string|null $description
 * @property string|null $size_m2
 * @property int|null $bedrooms
 * @property string $list_price
 * @property bool $vat_applies
 * @property string|null $nhbrc_enrolment
 * @property string $status available|reserved|sold|transferred|withdrawn
 * @property string $tenure sale|rental|both
 * @property string|null $market_rent
 * @property int $sort
 * @property-read Project $project
 */
class SaleUnit extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'reference', 'type', 'description', 'size_m2', 'bedrooms', 'list_price', 'vat_applies', 'nhbrc_enrolment', 'status', 'tenure', 'market_rent', 'sort'];

    protected function casts(): array
    {
        return ['market_rent' => 'decimal:2', 'list_price' => 'decimal:2', 'size_m2' => 'decimal:2', 'vat_applies' => 'boolean', 'bedrooms' => 'integer', 'sort' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Price excluding VAT, which is what the books and the feasibility work in. */
    public function netPrice(): float
    {
        return $this->vat_applies ? round((float) $this->list_price / 1.15, 2) : (float) $this->list_price;
    }

    /**
     * @return HasMany<Lease, $this>
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }
}
