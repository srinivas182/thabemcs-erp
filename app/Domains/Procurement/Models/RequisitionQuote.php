<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\Suppliers\Models\Supplier;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A supplier's quotation against a requisition (amount excl. VAT).
 *
 * @property int $id
 * @property int $requisition_id
 * @property int $supplier_id
 * @property string|null $reference
 * @property string $amount
 * @property int|null $lead_time_days
 * @property Carbon|null $valid_until
 * @property int|null $document_id
 * @property string|null $notes
 * @property bool $submitted_by_supplier
 * @property-read Supplier $supplier
 */
class RequisitionQuote extends Model
{
    use BelongsToCompany;

    protected $fillable = ['requisition_id', 'supplier_id', 'reference', 'amount', 'lead_time_days', 'valid_until', 'document_id', 'notes', 'submitted_by_supplier'];

    protected function casts(): array
    {
        return ['submitted_by_supplier' => 'boolean', 'amount' => 'decimal:2', 'valid_until' => 'date', 'lead_time_days' => 'integer'];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
