<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One attempt at loading a spreadsheet of opening data.
 *
 * @property int $id
 * @property string $ulid
 * @property string $type
 * @property string $file_name
 * @property int $rows_read
 * @property int $rows_imported
 * @property list<array{row: int, message: string}>|null $errors
 * @property string $status
 * @property int $created_by
 * @property Carbon $created_at
 */
class ImportRun extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['type', 'file_name', 'rows_read', 'rows_imported', 'errors', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['errors' => 'array', 'rows_read' => 'integer', 'rows_imported' => 'integer'];
    }
}
