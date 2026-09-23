<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Models;

use App\Domains\Funding\Models\Investor;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money an investor left in the business, moved into another project's funding.
 *
 * @property int $id
 * @property int $investor_id
 * @property int|null $distribution_line_id
 * @property int|null $from_project_id
 * @property int $to_funding_source_id
 * @property string $amount
 * @property Carbon $occurred_on
 * @property string|null $notes
 * @property int $recorded_by
 * @property-read Investor $investor
 */
class Reinvestment extends Model
{
    use BelongsToCompany;

    protected $fillable = ['investor_id', 'distribution_line_id', 'from_project_id', 'to_funding_source_id', 'amount', 'occurred_on', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_on' => 'date'];
    }

    /**
     * @return BelongsTo<Investor, $this>
     */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
