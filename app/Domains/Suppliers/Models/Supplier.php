<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Models;

use App\Domains\Platform\Enums\Province;
use App\Domains\Suppliers\Enums\SupplierType;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A contractor, subcontractor, supplier or service provider in the company's registry.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $trading_name
 * @property SupplierType $type
 * @property string|null $registration_number
 * @property string|null $vat_number
 * @property string|null $cidb_crs_number
 * @property int|null $cidb_grade
 * @property string|null $cidb_class
 * @property string|null $bbbee_level
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property Province|null $province
 * @property string $status active|suspended
 * @property string|null $notes
 * @property-read Collection<int, SupplierDocument> $complianceDocuments
 * @property-read Collection<int, SupplierRating> $ratings
 */
class Supplier extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'trading_name', 'type', 'registration_number', 'vat_number', 'cidb_crs_number', 'cidb_grade', 'cidb_class',
        'bbbee_level', 'contact_name', 'email', 'phone', 'province', 'status', 'notes',
    ];

    protected $attributes = ['status' => 'active'];

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
        return ['type' => SupplierType::class, 'province' => Province::class, 'cidb_grade' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'type', 'status', 'cidb_grade', 'bbbee_level'])->logOnlyDirty();
    }

    /**
     * @return HasMany<SupplierDocument, $this>
     */
    public function complianceDocuments(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    /**
     * @return HasMany<SupplierRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(SupplierRating::class)->latest();
    }
}
