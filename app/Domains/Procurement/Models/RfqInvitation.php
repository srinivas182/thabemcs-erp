<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request for quotation sent to a supplier by email. The supplier answers through a private link;
 * only a hash of the link's token is stored.
 *
 * @property int $id
 * @property int $requisition_id
 * @property int $supplier_id
 * @property string $email
 * @property string $token_hash
 * @property Carbon $closes_on
 * @property string|null $message
 * @property int $sent_by
 * @property Carbon $sent_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $declined_at
 * @property-read Requisition $requisition
 * @property-read Supplier $supplier
 * @property-read User $sender
 */
class RfqInvitation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['requisition_id', 'supplier_id', 'email', 'token_hash', 'closes_on', 'message', 'sent_by', 'sent_at'];

    protected function casts(): array
    {
        return ['closes_on' => 'date', 'sent_at' => 'datetime', 'opened_at' => 'datetime', 'responded_at' => 'datetime', 'declined_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isOpen(): bool
    {
        return $this->declined_at === null && $this->closes_on->copy()->endOfDay()->isFuture();
    }
}
