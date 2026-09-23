<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A joint inspection at the start or end of a lease, with the condition of each area.
 *
 * @property int $id
 * @property string $ulid
 * @property int $lease_id
 * @property string $type incoming|outgoing
 * @property Carbon $inspected_on
 * @property bool $tenant_present
 * @property list<array{area: string, condition: string, notes?: string|null}> $items
 * @property string|null $notes
 * @property int $conducted_by
 */
class LeaseInspection extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['lease_id', 'type', 'inspected_on', 'tenant_present', 'items', 'notes', 'conducted_by'];

    protected function casts(): array
    {
        return ['inspected_on' => 'date', 'tenant_present' => 'boolean', 'items' => 'array'];
    }
}
