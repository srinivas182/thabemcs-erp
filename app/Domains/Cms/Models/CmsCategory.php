<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class CmsCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'slug'];

    protected function casts(): array
    {
        return [];
    }
}
