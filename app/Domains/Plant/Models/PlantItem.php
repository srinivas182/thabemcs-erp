<?php

declare(strict_types=1);

namespace App\Domains\Plant\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An item of plant or equipment, owned or hired.
 *
 * @property int $id
 * @property string $ulid
 * @property string $asset_number
 * @property string $description
 * @property string $category
 * @property string|null $make_model
 * @property string|null $serial_number
 * @property string $ownership owned|hired
 * @property int|null $supplier_id
 * @property string|null $hire_rate_per_day
 * @property int|null $project_id
 * @property string $status available|on_site|in_service|broken|off_hired
 * @property int|null $service_interval_days
 * @property Carbon|null $next_service_on
 * @property-read Project|null $project
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, PlantEvent> $events
 */
class PlantItem extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['asset_number', 'description', 'category', 'make_model', 'serial_number', 'ownership', 'supplier_id', 'hire_rate_per_day', 'project_id', 'status', 'service_interval_days', 'next_service_on'];

    protected function casts(): array
    {
        return ['hire_rate_per_day' => 'decimal:2', 'next_service_on' => 'date', 'service_interval_days' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PlantEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PlantEvent::class)->latest('happened_on')->latest('id');
    }
}
