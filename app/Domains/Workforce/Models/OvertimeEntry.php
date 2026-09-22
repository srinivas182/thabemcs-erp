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
 * @property int|null $project_id
 * @property Carbon $worked_on
 * @property string $hours
 * @property string $rate_multiplier
 * @property string|null $reason
 * @property string $status
 * @property int $recorded_by
 * @property-read Employee $employee
 * @property-read Project|null $project
 */
class OvertimeEntry extends Model
{
    use BelongsToCompany;

    protected $fillable = ['employee_id', 'project_id', 'worked_on', 'hours', 'rate_multiplier', 'reason', 'status', 'recorded_by'];

    protected function casts(): array
    {
        return ['worked_on' => 'date', 'hours' => 'decimal:2', 'rate_multiplier' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
