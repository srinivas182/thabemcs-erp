<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $approval_request_id
 * @property int $sequence
 * @property string $role
 * @property string $decision pending|approved|rejected|skipped
 * @property int|null $decided_by
 * @property int|null $on_behalf_of
 * @property string|null $comment
 * @property Carbon|null $decided_at
 * @property Carbon|null $due_at
 * @property Carbon|null $escalated_at
 * @property-read ApprovalRequest $request
 * @property-read User|null $decider
 * @property-read User|null $principal
 */
class ApprovalStep extends Model
{
    use BelongsToCompany;

    protected $fillable = ['approval_request_id', 'sequence', 'role', 'decision', 'due_at'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'decided_at' => 'datetime', 'due_at' => 'datetime', 'escalated_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of');
    }
}
