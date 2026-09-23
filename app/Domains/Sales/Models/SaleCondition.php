<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A suspensive condition: the sale only becomes binding once every condition is met or waived.
 *
 * @property int $id
 * @property int $sale_agreement_id
 * @property string $type
 * @property string $description
 * @property Carbon $due_on
 * @property string $status open|met|waived|failed
 * @property Carbon|null $resolved_on
 * @property string|null $notes
 * @property-read SaleAgreement $agreement
 */
class SaleCondition extends Model
{
    use BelongsToCompany;

    protected $fillable = ['sale_agreement_id', 'type', 'description', 'due_on', 'status', 'resolved_on', 'notes'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'resolved_on' => 'date'];
    }

    /**
     * @return BelongsTo<SaleAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(SaleAgreement::class, 'sale_agreement_id');
    }
}
