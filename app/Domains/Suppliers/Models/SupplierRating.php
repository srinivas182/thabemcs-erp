<?php

declare(strict_types=1);

namespace App\Domains\Suppliers\Models;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $project_id
 * @property int $quality
 * @property int $timeliness
 * @property int $safety
 * @property string|null $comment
 * @property int|null $rated_by
 * @property Carbon $created_at
 * @property-read Project|null $project
 * @property-read User|null $rater
 */
class SupplierRating extends Model
{
    use BelongsToCompany;

    protected $fillable = ['supplier_id', 'project_id', 'quality', 'timeliness', 'safety', 'comment', 'rated_by'];

    protected function casts(): array
    {
        return ['quality' => 'integer', 'timeliness' => 'integer', 'safety' => 'integer'];
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
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    public function average(): float
    {
        return round(($this->quality + $this->timeliness + $this->safety) / 3, 1);
    }
}
