<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Models;

use App\Domains\Funding\Models\FundingSource;
use App\Domains\Funding\Models\Investor;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one investor receives from a distribution, split into capital, preferred return and profit.
 *
 * @property int $id
 * @property int $distribution_id
 * @property int $funding_source_id
 * @property int|null $investor_id
 * @property string $capital
 * @property string $preferred
 * @property string $profit
 * @property string $total
 * @property string|null $reference
 * @property-read FundingSource $source
 * @property-read Investor|null $investor
 */
class DistributionLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['distribution_id', 'funding_source_id', 'investor_id', 'capital', 'preferred', 'profit', 'total', 'reference'];

    protected function casts(): array
    {
        return ['capital' => 'decimal:2', 'preferred' => 'decimal:2', 'profit' => 'decimal:2', 'total' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<FundingSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class, 'funding_source_id');
    }

    /**
     * @return BelongsTo<Investor, $this>
     */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
