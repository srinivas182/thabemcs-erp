<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Platform\Enums\Province;
use App\Domains\Platform\Enums\QuotaType;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Models\Region;
use App\Domains\Platform\Services\QuotaService;
use App\Domains\Projects\Enums\DevelopmentType;
use App\Domains\Projects\Enums\ProjectStage;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A development project. The reference implementation of a company-owned model:
 * scoped to the current company, quota-checked on create, and fully audited.
 *
 * @property int $id
 * @property string $ulid
 * @property int $company_id
 * @property int|null $region_id
 * @property string $code
 * @property string $name
 * @property DevelopmentType|null $development_type
 * @property ProjectStage $stage
 * @property ProjectStatus $status
 * @property Province|null $province
 * @property string|null $town
 * @property string|null $estimated_value
 * @property Carbon|null $planned_start_date
 * @property Carbon|null $planned_completion_date
 */
class Project extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'region_id', 'code', 'name', 'development_type', 'stage', 'status', 'province', 'town',
        'latitude', 'longitude', 'estimated_value', 'planned_start_date', 'planned_completion_date',
        'description', 'project_manager_id',
    ];

    protected $attributes = [
        'stage' => 'plan',
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            // company_id has already been stamped by BelongsToCompany at this point.
            $company = Company::query()->findOrFail($project->company_id);
            app(QuotaService::class)->ensureCanAdd($company, QuotaType::Projects);
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'development_type' => DevelopmentType::class,
            'stage' => ProjectStage::class,
            'status' => ProjectStatus::class,
            'province' => Province::class,
            'estimated_value' => 'decimal:2',
            'planned_start_date' => 'date',
            'planned_completion_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }
}
