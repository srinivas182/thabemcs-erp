<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Somewhere to send events as they happen. The secret signs every delivery and is never shown again.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $url
 * @property list<string> $events
 * @property string $secret
 * @property bool $active
 * @property Carbon|null $last_delivered_at
 * @property string|null $last_error
 * @property int $failures
 * @property int $created_by
 */
class Webhook extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['name', 'url', 'events', 'secret', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['events' => 'array', 'secret' => 'encrypted', 'active' => 'boolean', 'last_delivered_at' => 'datetime', 'failures' => 'integer'];
    }
}
