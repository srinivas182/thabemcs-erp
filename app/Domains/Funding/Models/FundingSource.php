<?php

declare(strict_types=1);

namespace App\Domains\Funding\Models;

use App\Domains\Funding\Enums\FundingStatus;
use App\Domains\Funding\Enums\FundingType;
use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int|null $investor_id
 * @property FundingType $type
 * @property string $name
 * @property string $committed_amount
 * @property string|null $interest_rate
 * @property string|null $preferred_return_percent
 * @property string|null $profit_share_percent
 * @property Carbon|null $agreement_signed_on
 * @property FundingStatus $status
 * @property string|null $notes
 * @property-read Investor|null $investor
 * @property-read Project $project
 */
class FundingSource extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity;

    protected $fillable = ['project_id', 'investor_id', 'type', 'name', 'committed_amount', 'interest_rate', 'preferred_return_percent', 'profit_share_percent', 'agreement_signed_on', 'status', 'notes'];

    protected $attributes = ['status' => 'proposed'];

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
            'type' => FundingType::class,
            'preferred_return_percent' => 'decimal:2',
            'profit_share_percent' => 'decimal:2',
            'status' => FundingStatus::class,
            'committed_amount' => 'decimal:2',
            'interest_rate' => 'decimal:3',
            'agreement_signed_on' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Investor, $this>
     */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<FundingMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(FundingMovement::class)->orderByDesc('occurred_on');
    }
}
