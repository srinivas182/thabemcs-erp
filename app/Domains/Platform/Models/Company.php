<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Domains\Platform\Enums\CompanyStatus;
use App\Domains\Platform\Enums\Module;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A legal entity within the group (tenant). Created and managed by the Super Admin.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $registration_number CIPC registration number
 * @property string|null $vat_number
 * @property CompanyStatus $status
 * @property int|null $max_projects Null means unlimited
 * @property int|null $max_users Null means unlimited
 * @property int|null $max_storage_mb Null means unlimited
 * @property list<string>|null $modules Enabled module keys
 * @property array<string, mixed>|null $settings
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'legal_name', 'registration_number', 'vat_number', 'status',
        'max_projects', 'max_users', 'max_storage_mb', 'modules', 'settings',
    ];

    /**
     * Only the public ULID is generated automatically; the numeric id stays the primary key.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'modules' => 'array',
            'settings' => 'array',
            'max_projects' => 'integer',
            'max_users' => 'integer',
            'max_storage_mb' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Region, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    public function hasModule(Module $module): bool
    {
        return in_array($module->value, $this->modules ?? [], true);
    }

    /**
     * @return list<Module>
     */
    public function enabledModules(): array
    {
        return array_values(array_filter(
            Module::cases(),
            fn (Module $m): bool => $this->hasModule($m),
        ));
    }
}
