<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Models;

use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Precomputed figures for one project, refreshed by queued jobs when anything affecting them changes
 * and nightly for date-dependent values. Dashboards and the command centre read only this table.
 *
 * @property int $id
 * @property int $project_id
 * @property string $budget
 * @property string $spent
 * @property string|null $used_percent
 * @property string $paid
 * @property int $high_risks
 * @property int $open_incidents
 * @property int $open_snags
 * @property int $behind_activities
 * @property Carbon|null $forecast_finish
 * @property bool $late
 * @property string $health red|amber|green
 * @property int $severity
 * @property Carbon|null $refreshed_at
 * @property-read Project $project
 */
class ProjectMetric extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'budget', 'spent', 'used_percent', 'paid', 'high_risks', 'open_incidents', 'open_snags', 'behind_activities', 'forecast_finish', 'late', 'health', 'severity', 'refreshed_at'];

    protected function casts(): array
    {
        return ['budget' => 'decimal:2', 'spent' => 'decimal:2', 'used_percent' => 'decimal:1', 'paid' => 'decimal:2', 'forecast_finish' => 'date', 'late' => 'boolean', 'refreshed_at' => 'datetime', 'high_risks' => 'integer', 'open_incidents' => 'integer', 'open_snags' => 'integer', 'behind_activities' => 'integer', 'severity' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
