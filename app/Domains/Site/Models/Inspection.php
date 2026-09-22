<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A quality or safety inspection.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $kind
 * @property string $title
 * @property string|null $location
 * @property string $result
 * @property string|null $findings
 * @property Carbon $inspected_on
 * @property int|null $inspected_by
 * @property-read User|null $inspector
 * @property-read Project $project
 */
class Inspection extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['client_id', 'project_id', 'kind', 'title', 'location', 'result', 'findings', 'inspected_on', 'inspected_by'];

    protected function casts(): array
    {
        return ['inspected_on' => 'date'];
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
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
