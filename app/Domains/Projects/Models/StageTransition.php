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
 * Permanent record of a project moving from one stage to the next, and who approved it.
 *
 * @property int $id
 * @property int $project_id
 * @property ProjectStage $from_stage
 * @property ProjectStage $to_stage
 * @property int|null $approved_by
 * @property string|null $comment
 * @property Carbon $created_at
 * @property-read User|null $approver
 */
class StageTransition extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $fillable = ['project_id', 'from_stage', 'to_stage', 'approved_by', 'comment'];

    protected function casts(): array
    {
        return [
            'from_stage' => ProjectStage::class,
            'to_stage' => ProjectStage::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
