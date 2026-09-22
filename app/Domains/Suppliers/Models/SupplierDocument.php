<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Models;

use App\Domains\Documents\Models\Document;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property string $type
 * @property string|null $reference
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property int|null $document_id
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon $created_at
 * @property-read Supplier $supplier
 * @property-read Document|null $document
 * @property-read User|null $verifier
 */
class SupplierDocument extends Model
{
    use BelongsToCompany;

    protected $fillable = ['supplier_id', 'type', 'reference', 'issued_on', 'expires_on', 'document_id', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date', 'verified_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
