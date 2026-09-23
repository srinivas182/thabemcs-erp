<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step of the transfer pipeline, from instructing the conveyancer to registration.
 *
 * @property int $id
 * @property int $sale_agreement_id
 * @property string $step
 * @property int $sort
 * @property Carbon|null $due_on
 * @property Carbon|null $completed_on
 * @property string|null $notes
 * @property-read SaleAgreement $agreement
 */
class TransferStep extends Model
{
    use BelongsToCompany;

    protected $fillable = ['sale_agreement_id', 'step', 'sort', 'due_on', 'completed_on', 'notes'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_on' => 'date', 'sort' => 'integer'];
    }

    /**
     * @return BelongsTo<SaleAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(SaleAgreement::class, 'sale_agreement_id');
    }
}
