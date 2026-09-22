<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Projects\Enums\ProjectStage;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One checklist item that must be completed before a project can leave a stage.
 *
 * @property int $id
 * @property int $project_id
 * @property ProjectStage $stage
 * @property string $title
 * @property bool $is_required
 * @property int $sort
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property string|null $notes
 * @property-read User|null $completer
 */
class StageGateItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'stage', 'title', 'is_required', 'sort', 'notes'];

    protected function casts(): array
    {
        return [
            'stage' => ProjectStage::class,
            'is_required' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
