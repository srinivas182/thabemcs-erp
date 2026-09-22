<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report designed by a user: a dataset, chosen columns, filters, sort order and totals.
 *
 * @property int $id
 * @property string $name
 * @property string $dataset
 * @property array{columns: list<string>, filters?: list<array{field: string, op: string, value: string}>, sort?: string|null, direction?: string, totals?: bool, subtitle?: string|null} $config
 * @property bool $shared
 * @property int $created_by
 * @property-read User $creator
 */
class CustomReport extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'dataset', 'config', 'shared', 'created_by'];

    protected function casts(): array
    {
        return ['config' => 'array', 'shared' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
