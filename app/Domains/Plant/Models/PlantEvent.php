<?php

declare(strict_types=1);

namespace App\Domains\Plant\Models;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plant_item_id
 * @property string $type moved|serviced|breakdown|off_hired|repaired
 * @property int|null $project_id
 * @property Carbon $happened_on
 * @property string|null $cost
 * @property string|null $notes
 * @property int $recorded_by
 * @property Carbon $created_at
 * @property-read Project|null $project
 * @property-read User $recorder
 */
class PlantEvent extends Model
{
    use BelongsToCompany;

    protected $fillable = ['plant_item_id', 'type', 'project_id', 'happened_on', 'cost', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['happened_on' => 'date', 'cost' => 'decimal:2'];
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
