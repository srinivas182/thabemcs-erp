<?php

declare(strict_types=1);

namespace App\Domains\Funding\Models;

use App\Domains\Funding\Enums\InvestorType;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A person or entity that puts money into the company's developments.
 * The ID / registration number is personal information under POPIA and is encrypted at rest.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property InvestorType $entity_type
 * @property string|null $registration_number
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $fica_verified_at
 * @property int|null $fica_verified_by
 * @property string|null $notes
 */
class Investor extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'entity_type', 'registration_number', 'contact_person', 'email', 'phone', 'notes'];

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
            'entity_type' => InvestorType::class,
            'registration_number' => 'encrypted',
            'fica_verified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Never write the ID / registration number into the audit log.
        return LogOptions::defaults()->logOnly(['name', 'entity_type', 'contact_person', 'email', 'phone', 'fica_verified_at'])->logOnlyDirty();
    }

    /**
     * @return HasMany<FundingSource, $this>
     */
    public function fundingSources(): HasMany
    {
        return $this->hasMany(FundingSource::class);
    }

    /**
     * Registration number with all but the last four characters hidden.
     */
    public function maskedRegistrationNumber(): ?string
    {
        $value = $this->registration_number;

        return $value === null ? null : str_repeat('•', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
