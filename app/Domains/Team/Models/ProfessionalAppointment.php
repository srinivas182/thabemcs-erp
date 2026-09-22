<?php

declare(strict_types=1);

namespace App\Domains\Team\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Team\Enums\Discipline;
use App\Domains\Team\Enums\FeeBasis;
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
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property Discipline $discipline
 * @property string $firm_name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $registration_body
 * @property string|null $registration_number
 * @property Carbon|null $registration_verified_at
 * @property int|null $registration_verified_by
 * @property FeeBasis $fee_basis
 * @property string|null $fee_percentage
 * @property string|null $agreed_fee
 * @property Carbon|null $appointed_on
 * @property string $status
 * @property-read Project $project
 * @property-read Collection<int, FeeClaim> $claims
 */
class ProfessionalAppointment extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity;

    protected $fillable = [
        'project_id', 'discipline', 'firm_name', 'contact_name', 'email', 'phone', 'registration_body', 'registration_number',
        'fee_basis', 'fee_percentage', 'agreed_fee', 'appointed_on', 'status',
    ];

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
            'discipline' => Discipline::class,
            'fee_basis' => FeeBasis::class,
            'fee_percentage' => 'decimal:3',
            'agreed_fee' => 'decimal:2',
            'appointed_on' => 'date',
            'registration_verified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['discipline', 'firm_name', 'status', 'agreed_fee', 'registration_verified_at'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<FeeClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(FeeClaim::class)->orderByDesc('submitted_on');
    }
}
