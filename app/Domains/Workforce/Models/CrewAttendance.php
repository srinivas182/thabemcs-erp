<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daily attendance of a worker, recorded by a supervisor (workers need no login).
 *
 * @property int $id
 * @property int $project_id
 * @property int $employee_id
 * @property string $client_id
 * @property Carbon $worked_on
 * @property string|null $time_in
 * @property string|null $time_out
 * @property string $status present|absent|sick|leave
 * @property int $recorded_by
 * @property-read Employee $employee
 * @property-read Project $project
 */
class CrewAttendance extends Model
{
    use BelongsToCompany;

    protected $table = 'crew_attendance';

    protected $fillable = ['project_id', 'employee_id', 'client_id', 'worked_on', 'time_in', 'time_out', 'status', 'recorded_by'];

    protected function casts(): array
    {
        return ['worked_on' => 'date'];
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
