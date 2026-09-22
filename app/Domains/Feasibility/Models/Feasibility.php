<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Models;

use App\Domains\Feasibility\Enums\FeasibilityStatus;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A feasibility scenario for a project (e.g. "Base case", "Slower sales").
 * One approved scenario per project is the baseline used for budgets and funding.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $name
 * @property int $duration_months
 * @property int|null $units
 * @property FeasibilityStatus $status
 * @property bool $is_baseline
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $notes
 * @property-read Project $project
 * @property-read User|null $approver
 * @property-read Collection<int, FeasibilityLine> $lines
 */
class Feasibility extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity;

    protected $fillable = ['project_id', 'name', 'duration_months', 'units', 'notes'];

    protected $attributes = ['status' => 'draft', 'is_baseline' => false];

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
            'status' => FeasibilityStatus::class,
            'is_baseline' => 'boolean',
            'approved_at' => 'datetime',
            'duration_months' => 'integer',
            'units' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'status', 'is_baseline', 'duration_months'])->logOnlyDirty();
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<FeasibilityLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(FeasibilityLine::class)->orderBy('sort');
    }

    public function isApproved(): bool
    {
        return $this->status === FeasibilityStatus::Approved;
    }
}
