<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How long a kind of personal record is kept before the monthly clean-up removes or anonymises it.
 *
 * @property int $id
 * @property string $record
 * @property int $keep_months
 * @property Carbon|null $last_run_at
 * @property int $last_run_count
 */
class RetentionRule extends Model
{
    use BelongsToCompany;

    protected $fillable = ['record', 'keep_months'];

    protected function casts(): array
    {
        return ['keep_months' => 'integer', 'last_run_at' => 'datetime', 'last_run_count' => 'integer'];
    }
}
