<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 */
class Unit extends Model
{
    use BelongsToCompany;

    protected $fillable = ['code', 'name'];

    protected function casts(): array
    {
        return [];
    }
}
