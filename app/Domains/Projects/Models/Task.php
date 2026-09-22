<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Projects\Enums\TaskPriority;
use App\Domains\Projects\Enums\TaskStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $project_id
 * @property int|null $meeting_id
 * @property Carbon|null $escalated_at
 * @property string $title
 * @property string|null $description
 * @property int|null $assignee_id
 * @property Carbon|null $due_date
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property-read User|null $assignee
 * @property-read Project|null $project
 */
class Task extends Model
{
    use BelongsToCompany, HasUlids;

    protected $fillable = ['project_id', 'meeting_id', 'title', 'description', 'assignee_id', 'due_date', 'status', 'priority', 'created_by'];

    protected $attributes = ['status' => 'open', 'priority' => 'normal'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->due_date !== null && $this->due_date->isBefore(Carbon::today());
    }
}
