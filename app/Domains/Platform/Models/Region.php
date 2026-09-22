<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Domains\Platform\Enums\Province;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * An operating region or division within a company (e.g. "Gauteng North").
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property Province|null $province
 */
class Region extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'province'];

    protected function casts(): array
    {
        return ['province' => Province::class];
    }
}
