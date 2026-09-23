<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * How one person wants to be told about things.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $email_immediately
 * @property bool $daily_digest
 * @property list<string>|null $muted
 */
class NotificationPreference extends Model
{
    use BelongsToCompany;

    protected $fillable = ['user_id', 'email_immediately', 'daily_digest', 'muted'];

    protected function casts(): array
    {
        return ['email_immediately' => 'boolean', 'daily_digest' => 'boolean', 'muted' => 'array'];
    }
}
