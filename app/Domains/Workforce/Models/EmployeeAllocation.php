<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $project_id
 * @property Carbon $from_date
 * @property Carbon|null $to_date
 * @property string|null $role_on_site
 * @property-read Project $project
 */
class EmployeeAllocation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['employee_id', 'project_id', 'from_date', 'to_date', 'role_on_site'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
