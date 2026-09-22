<?php

declare(strict_types=1);

namespace App\Domains\Safety\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Safety\Enums\IncidentType;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An incident or near miss. Reportable incidents must be reported to the Department of
 * Employment and Labour (OHS Act s24) and the Compensation Fund where applicable.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $client_id
 * @property IncidentType $type
 * @property Carbon $occurred_at
 * @property string|null $location
 * @property string $description
 * @property string|null $immediate_action
 * @property string|null $person_involved
 * @property bool $reportable
 * @property Carbon|null $reported_to_authority_at
 * @property string|null $root_cause
 * @property string|null $corrective_action
 * @property string $status
 * @property int|null $reported_by
 * @property-read User|null $reporter
 * @property-read Project $project
 */
class SafetyIncident extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'client_id', 'type', 'occurred_at', 'location', 'description', 'immediate_action', 'person_involved', 'reportable', 'reported_to_authority_at', 'root_cause', 'corrective_action', 'status', 'reported_by'];

    protected function casts(): array
    {
        return ['type' => IncidentType::class, 'occurred_at' => 'datetime', 'reportable' => 'boolean', 'reported_to_authority_at' => 'datetime'];
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
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
