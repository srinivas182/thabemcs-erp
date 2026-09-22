<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use App\Domains\Projects\Models\Project;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A defect to be fixed by a contractor, then verified.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int|null $inspection_id
 * @property string|null $location
 * @property string $description
 * @property int|null $supplier_id
 * @property Carbon|null $due_on
 * @property string $status
 * @property Carbon|null $fixed_at
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property-read Project $project
 */
class Snag extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['client_id', 'project_id', 'inspection_id', 'location', 'description', 'supplier_id', 'due_on', 'status'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'fixed_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
