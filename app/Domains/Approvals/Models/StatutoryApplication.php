<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Models;

use App\Domains\Approvals\Enums\ApplicationStatus;
use App\Domains\Approvals\Enums\ApplicationType;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An application to a municipality or other authority, tracked from preparation to decision and expiry.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property ApplicationType $type
 * @property string|null $description
 * @property string|null $authority
 * @property string|null $reference_number
 * @property ApplicationStatus $status
 * @property Carbon|null $submitted_on
 * @property Carbon|null $expected_decision_on
 * @property Carbon|null $decision_on
 * @property Carbon|null $valid_until
 * @property string|null $conditions
 * @property int|null $responsible_id
 * @property-read Project $project
 * @property-read User|null $responsible
 */
class StatutoryApplication extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity;

    protected $fillable = [
        'project_id', 'type', 'description', 'authority', 'reference_number', 'status', 'submitted_on',
        'expected_decision_on', 'decision_on', 'valid_until', 'conditions', 'responsible_id',
    ];

    protected $attributes = ['status' => 'preparing'];

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
            'type' => ApplicationType::class,
            'status' => ApplicationStatus::class,
            'submitted_on' => 'date',
            'expected_decision_on' => 'date',
            'decision_on' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['type', 'status', 'reference_number', 'decision_on', 'valid_until'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * Days until an approval lapses; negative when already lapsed. Null when there is no expiry.
     */
    public function daysToExpiry(): ?int
    {
        return $this->valid_until === null ? null : (int) Carbon::today()->diffInDays($this->valid_until, false);
    }

    public function isDecisionOverdue(): bool
    {
        return in_array($this->status, [ApplicationStatus::Submitted, ApplicationStatus::Query], true)
            && $this->expected_decision_on !== null
            && $this->expected_decision_on->isBefore(Carbon::today());
    }
}
