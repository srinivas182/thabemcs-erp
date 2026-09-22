<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A geotagged photo from site, optionally attached to a delivery, snag or incident.
 *
 * @property int $id
 * @property int $project_id
 * @property string $client_id
 * @property string|null $attachable_type
 * @property int|null $attachable_id
 * @property string $disk
 * @property string $path
 * @property int $size_bytes
 * @property string|null $caption
 * @property string|null $latitude
 * @property string|null $longitude
 * @property Carbon $captured_at
 * @property int|null $created_by
 * @property-read User|null $author
 * @property-read Project $project
 */
class SitePhoto extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'client_id', 'attachable_type', 'attachable_id', 'disk', 'path', 'size_bytes', 'caption', 'latitude', 'longitude', 'captured_at', 'created_by'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'size_bytes' => 'integer'];
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
