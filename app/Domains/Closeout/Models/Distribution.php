<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Models;

use App\Domains\Projects\Models\Project;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A payment of cash to the investors in a project, worked out through the waterfall.
 *
 * @property int $id
 * @property string $ulid
 * @property int $number
 * @property int $project_id
 * @property Carbon $declared_on
 * @property string $amount
 * @property string $status draft|approved|paid
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_on
 * @property string|null $notes
 * @property int $created_by
 * @property-read Project $project
 * @property-read Collection<int, DistributionLine> $lines
 */
class Distribution extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['number', 'project_id', 'declared_on', 'amount', 'status', 'approved_by', 'approved_at', 'paid_on', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['declared_on' => 'date', 'approved_at' => 'datetime', 'paid_on' => 'date', 'amount' => 'decimal:2', 'number' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<DistributionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DistributionLine::class);
    }

    public function reference(): string
    {
        return 'D-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }
}
