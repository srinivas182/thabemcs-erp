<?php

declare(strict_types=1);

namespace App\Domains\Safety\Models;

use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $topic
 * @property Carbon $held_on
 * @property int $attendees
 * @property string|null $presenter
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property-read Project $project
 */
class ToolboxTalk extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'topic', 'held_on', 'attendees', 'presenter', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['held_on' => 'date', 'attendees' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
