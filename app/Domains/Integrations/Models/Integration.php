<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A company's connection to an external system. Credentials are encrypted at rest.
 *
 * @property int $id
 * @property string $provider sage_za|simplepay
 * @property bool $enabled
 * @property array<string, string>|null $credentials
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 */
class Integration extends Model
{
    use BelongsToCompany;

    protected $fillable = ['provider', 'enabled', 'credentials', 'settings'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'credentials' => 'encrypted:array', 'settings' => 'array', 'last_synced_at' => 'datetime'];
    }
}
