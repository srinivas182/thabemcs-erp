<?php

declare(strict_types=1);

namespace App\Domains\Finance\Models;

use App\Domains\Projects\Models\Project;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cost code on a project budget (excl. VAT).
 *
 * @property int $id
 * @property int $project_id
 * @property string $code
 * @property string $description
 * @property string|null $category
 * @property string $original_amount
 * @property string|null $alert_level
 * @property int $sort
 * @property-read Project $project
 */
class BudgetLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'code', 'description', 'category', 'original_amount', 'sort'];

    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2', 'sort' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<VariationOrder, $this>
     */
    public function variations(): HasMany
    {
        return $this->hasMany(VariationOrder::class);
    }
}
