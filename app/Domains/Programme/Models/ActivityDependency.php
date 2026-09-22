<?php

declare(strict_types=1);

namespace App\Domains\Programme\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Finish-to-start link with an optional lag (working days).
 *
 * @property int $id
 * @property int $predecessor_id
 * @property int $successor_id
 * @property int $lag_days
 * @property-read ProgrammeActivity $predecessor
 */
class ActivityDependency extends Model
{
    use BelongsToCompany;

    protected $fillable = ['predecessor_id', 'successor_id', 'lag_days'];

    protected function casts(): array
    {
        return ['lag_days' => 'integer'];
    }

    /**
     * @return BelongsTo<ProgrammeActivity, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(ProgrammeActivity::class, 'predecessor_id');
    }
}
