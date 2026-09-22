<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One record sent (or attempted) to an external system; prevents sending the same record twice.
 *
 * @property int $id
 * @property string $provider
 * @property string $entity_type
 * @property int $entity_id
 * @property string $status sent|failed
 * @property string|null $external_id
 * @property string|null $error
 * @property int $attempts
 * @property Carbon $updated_at
 */
class IntegrationSync extends Model
{
    use BelongsToCompany;

    protected $fillable = ['provider', 'entity_type', 'entity_id', 'status', 'external_id', 'error', 'attempts'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'entity_id' => 'integer'];
    }
}
