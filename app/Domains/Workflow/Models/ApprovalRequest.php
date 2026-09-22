<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $policy
 * @property string $approvable_type
 * @property int $approvable_id
 * @property string $amount
 * @property string $status pending|approved|rejected|cancelled
 * @property int $current_step
 * @property int $requested_by
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property-read Model $approvable
 * @property-read User $requester
 * @property-read Collection<int, ApprovalStep> $steps
 */
class ApprovalRequest extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['policy', 'approvable_type', 'approvable_id', 'amount', 'status', 'current_step', 'requested_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'current_step' => 'integer', 'completed_at' => 'datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<ApprovalStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('sequence');
    }
}
