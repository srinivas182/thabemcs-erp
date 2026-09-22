<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Site\Enums\Weather;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One diary per project per day, captured on site (offline-first).
 *
 * @property int $id
 * @property int $project_id
 * @property string $client_id
 * @property Carbon $diary_date
 * @property Weather $weather
 * @property string|null $temperature_max
 * @property string|null $rain_mm
 * @property bool $weather_auto
 * @property int $workers_on_site
 * @property string $work_completed
 * @property string|null $delays
 * @property Carbon $captured_at
 * @property int|null $created_by
 * @property-read User|null $author
 * @property-read Project $project
 */
class SiteDiary extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'client_id', 'diary_date', 'weather', 'temperature_max', 'rain_mm', 'weather_auto', 'workers_on_site', 'work_completed', 'delays', 'captured_at', 'created_by'];

    protected function casts(): array
    {
        return ['diary_date' => 'date', 'weather' => Weather::class, 'weather_auto' => 'boolean', 'temperature_max' => 'decimal:1', 'rain_mm' => 'decimal:1', 'workers_on_site' => 'integer', 'captured_at' => 'datetime'];
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
