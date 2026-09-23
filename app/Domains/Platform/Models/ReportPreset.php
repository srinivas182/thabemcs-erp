<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved layout for a standard report: which columns to show, in what order, and default filters.
 *
 * @property int $id
 * @property string $report_key
 * @property string $name
 * @property list<string> $columns
 * @property array<string, string>|null $filters
 * @property bool $is_default
 * @property int $created_by
 */
class ReportPreset extends Model
{
    use BelongsToCompany;

    protected $fillable = ['report_key', 'name', 'columns', 'filters', 'is_default', 'created_by'];

    protected function casts(): array
    {
        return ['columns' => 'array', 'filters' => 'array', 'is_default' => 'boolean'];
    }
}
