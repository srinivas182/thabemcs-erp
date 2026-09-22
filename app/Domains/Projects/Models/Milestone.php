<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Projects\Enums\ProjectStage;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A key date on the project programme.
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property ProjectStage|null $stage
 * @property Carbon $planned_date
 * @property Carbon|null $forecast_date
 * @property Carbon|null $completed_on
 */
class Milestone extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'title', 'stage', 'planned_date', 'forecast_date', 'completed_on'];

    protected function casts(): array
    {
        return [
            'stage' => ProjectStage::class,
            'planned_date' => 'date',
            'forecast_date' => 'date',
            'completed_on' => 'date',
        ];
    }

    /**
     * Days late against the planned date (positive = late). Null when on time or not yet due.
     */
    public function daysLate(): ?int
    {
        $reference = $this->completed_on ?? $this->forecast_date ?? ($this->planned_date->isPast() ? Carbon::today() : null);

        if ($reference === null) {
            return null;
        }

        $late = (int) $this->planned_date->diffInDays($reference, false);

        return $late > 0 ? $late : null;
    }
}
