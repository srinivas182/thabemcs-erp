<?php

declare(strict_types=1);

namespace App\Domains\Funding\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Money received (contribution / drawdown) or paid back (repayment / distribution).
 *
 * @property int $id
 * @property int $funding_source_id
 * @property string $direction in|out
 * @property string $amount
 * @property Carbon $occurred_on
 * @property string|null $reference
 * @property int|null $recorded_by
 */
class FundingMovement extends Model
{
    use BelongsToCompany;

    protected $fillable = ['funding_source_id', 'direction', 'amount', 'occurred_on', 'reference', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_on' => 'date'];
    }
}
