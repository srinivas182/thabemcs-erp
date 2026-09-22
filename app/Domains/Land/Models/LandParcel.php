<?php

declare(strict_types=1);

namespace App\Domains\Land\Models;

use App\Domains\Land\Enums\LandStatus;
use App\Domains\Platform\Enums\Province;
use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A piece of land being considered, negotiated or acquired.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $project_id
 * @property string $name
 * @property string|null $property_description
 * @property string|null $title_deed_number
 * @property Province|null $province
 * @property string|null $town
 * @property string|null $size_m2
 * @property string|null $current_zoning
 * @property string|null $seller_name
 * @property string|null $asking_price
 * @property string|null $offer_price
 * @property LandStatus $status
 * @property Carbon|null $offer_date
 * @property Carbon|null $acceptance_date
 * @property Carbon|null $transfer_date
 * @property string|null $notes
 * @property-read Project|null $project
 * @property-read Collection<int, LandCheck> $checks
 */
class LandParcel extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id', 'name', 'property_description', 'title_deed_number', 'province', 'town', 'size_m2', 'current_zoning',
        'seller_name', 'asking_price', 'offer_price', 'offer_date', 'acceptance_date', 'transfer_date', 'notes',
    ];

    protected $attributes = ['status' => 'identified'];

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
            'status' => LandStatus::class,
            'province' => Province::class,
            'size_m2' => 'decimal:2',
            'asking_price' => 'decimal:2',
            'offer_price' => 'decimal:2',
            'offer_date' => 'date',
            'acceptance_date' => 'date',
            'transfer_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'status', 'offer_price', 'project_id', 'transfer_date'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<LandCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(LandCheck::class)->orderBy('sort');
    }
}
