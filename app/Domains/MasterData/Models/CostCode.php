<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Company cost code library, used when building project budgets.
 *
 * @property int $id
 * @property string $code
 * @property string $description
 * @property string|null $category
 * @property bool $active
 */
class CostCode extends Model
{
    use BelongsToCompany;

    protected $fillable = ['code', 'description', 'category', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
