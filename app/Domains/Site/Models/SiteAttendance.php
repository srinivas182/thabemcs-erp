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
 * A sign-in or sign-out on site, with the phone's location and a selfie.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $client_id
 * @property string $direction
 * @property Carbon $captured_at
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $accuracy_m
 * @property int|null $distance_m
 * @property bool|null $within_geofence
 * @property string|null $selfie_path
 * @property-read User $user
 * @property-read Project $project
 */
class SiteAttendance extends Model
{
    use BelongsToCompany;

    protected $table = 'site_attendance';

    protected $fillable = ['project_id', 'user_id', 'client_id', 'direction', 'captured_at', 'latitude', 'longitude', 'accuracy_m', 'distance_m', 'within_geofence', 'selfie_path'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'within_geofence' => 'boolean', 'accuracy_m' => 'integer', 'distance_m' => 'integer'];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
