<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * The navigation at the top of the site, and the footer.
 *
 * @property int $id
 * @property string $location primary|footer
 * @property list<array{label: string, link: string, children?: list<array{label: string, link: string}>}> $items
 */
class CmsMenu extends Model
{
    use BelongsToCompany;

    protected $fillable = ['location', 'items'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}
