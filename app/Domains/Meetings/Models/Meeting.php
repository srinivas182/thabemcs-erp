<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Site, progress, design, safety or client meeting with minutes. Action items are project tasks.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $number
 * @property string $type
 * @property string $title
 * @property Carbon $held_at
 * @property string|null $location
 * @property string|null $attendees
 * @property string|null $apologies
 * @property string|null $minutes
 * @property string $status draft|issued
 * @property int $recorded_by
 * @property Carbon|null $issued_at
 * @property-read Project $project
 * @property-read User $recorder
 * @property-read Collection<int, Task> $actions
 */
class Meeting extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'number', 'type', 'title', 'held_at', 'location', 'attendees', 'apologies', 'minutes', 'status', 'recorded_by'];

    protected function casts(): array
    {
        return ['held_at' => 'datetime', 'issued_at' => 'datetime', 'number' => 'integer'];
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

    /**
     * @return HasMany<Task, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
