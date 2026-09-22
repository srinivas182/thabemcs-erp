<?php

declare(strict_types=1);

namespace App\Domains\Programme\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An activity on the construction programme (Gantt chart). Duration 0 = milestone.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string|null $wbs
 * @property string $name
 * @property Carbon $planned_start
 * @property int $duration_days
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_finish
 * @property int $percent_complete
 * @property int|null $owner_id
 * @property int|null $supplier_id
 * @property string|null $budget_value
 * @property int $sort
 * @property-read Project $project
 * @property-read User|null $owner
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, ActivityDependency> $predecessors
 */
class ProgrammeActivity extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'wbs', 'name', 'planned_start', 'duration_days', 'actual_start', 'actual_finish', 'percent_complete', 'owner_id', 'supplier_id', 'budget_value', 'sort'];

    protected function casts(): array
    {
        return ['planned_start' => 'date', 'actual_start' => 'date', 'actual_finish' => 'date', 'duration_days' => 'integer', 'percent_complete' => 'integer', 'budget_value' => 'decimal:2', 'sort' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<ActivityDependency, $this>
     */
    public function predecessors(): HasMany
    {
        return $this->hasMany(ActivityDependency::class, 'successor_id');
    }
}
