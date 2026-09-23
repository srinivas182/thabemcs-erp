<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One item on a project's close-out checklist.
 *
 * @property int $id
 * @property int $project_id
 * @property string $key
 * @property string $label
 * @property string $group
 * @property bool $required
 * @property int $sort
 * @property Carbon|null $completed_on
 * @property int|null $completed_by
 * @property int|null $document_id
 * @property string|null $notes
 */
class CloseoutItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'key', 'label', 'group', 'required', 'sort', 'completed_on', 'completed_by', 'document_id', 'notes'];

    protected function casts(): array
    {
        return ['completed_on' => 'date', 'required' => 'boolean', 'sort' => 'integer'];
    }
}
