<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Projects\Enums\RiskKind;
use App\Domains\Projects\Enums\RiskStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A project risk (may happen) or issue (is happening), scored on a 5 x 5 matrix.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property RiskKind $kind
 * @property string $title
 * @property string|null $description
 * @property int $likelihood
 * @property int $impact
 * @property string|null $mitigation
 * @property int|null $owner_id
 * @property RiskStatus $status
 * @property Carbon|null $review_date
 * @property-read User|null $owner
 * @property-read Project|null $project
 */
class Risk extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity;

    protected $fillable = ['project_id', 'kind', 'title', 'description', 'likelihood', 'impact', 'mitigation', 'owner_id', 'status', 'review_date'];

    protected $attributes = ['kind' => 'risk', 'status' => 'open', 'likelihood' => 3, 'impact' => 3];

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
            'kind' => RiskKind::class,
            'status' => RiskStatus::class,
            'likelihood' => 'integer',
            'impact' => 'integer',
            'review_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function score(): int
    {
        return $this->likelihood * $this->impact;
    }

    /**
     * Rating bands for a 5 x 5 matrix.
     */
    public function rating(): string
    {
        return match (true) {
            $this->score() >= 20 => 'critical',
            $this->score() >= 10 => 'high',
            $this->score() >= 5 => 'medium',
            default => 'low',
        };
    }
}
