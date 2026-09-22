<?php

declare(strict_types=1);

namespace App\Domains\Safety\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One item of the project's health and safety file.
 *
 * @property int $id
 * @property int $project_id
 * @property string $item
 * @property string $status missing|in_place|not_applicable
 * @property int|null $document_id
 * @property Carbon|null $review_due_on
 * @property string|null $notes
 * @property int|null $updated_by
 */
class SafetyFileItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'item', 'status', 'document_id', 'review_due_on', 'notes', 'updated_by'];

    protected function casts(): array
    {
        return ['review_due_on' => 'date'];
    }
}
