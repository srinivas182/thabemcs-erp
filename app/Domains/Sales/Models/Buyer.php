<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone buying, from first enquiry through to registration. The ID or registration number is
 * encrypted (POPIA); FICA must be verified before a sale can be made unconditional.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $entity_type individual|company|trust
 * @property string|null $id_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string $status enquiry|qualified|reserved|purchaser|lost
 * @property string|null $source
 * @property int|null $agent_supplier_id
 * @property int|null $owner_id
 * @property bool $fica_verified
 * @property Carbon|null $fica_verified_on
 * @property string|null $notes
 * @property-read User|null $owner
 * @property-read Supplier|null $agent
 */
class Buyer extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['name', 'entity_type', 'id_number', 'email', 'phone', 'address', 'status', 'source', 'agent_supplier_id', 'owner_id', 'fica_verified', 'fica_verified_on', 'notes'];

    protected function casts(): array
    {
        return ['id_number' => 'encrypted', 'fica_verified' => 'boolean', 'fica_verified_on' => 'date'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'agent_supplier_id');
    }
}
