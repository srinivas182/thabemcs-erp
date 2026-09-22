<?php

declare(strict_types=1);

namespace App\Domains\Programme\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Weekly earned-value reading for the S-curve history.
 *
 * @property int $id
 * @property int $project_id
 * @property Carbon $taken_on
 * @property string $planned_value
 * @property string $earned_value
 * @property string $actual_cost
 */
class ProgressSnapshot extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'taken_on', 'planned_value', 'earned_value', 'actual_cost'];

    protected function casts(): array
    {
        return ['taken_on' => 'date', 'planned_value' => 'decimal:2', 'earned_value' => 'decimal:2', 'actual_cost' => 'decimal:2'];
    }
}
