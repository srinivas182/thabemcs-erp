<?php

declare(strict_types=1);

namespace App\Domains\Land\Models;

use App\Domains\Land\Enums\CheckResult;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $land_parcel_id
 * @property string $key
 * @property string $title
 * @property bool $is_required
 * @property CheckResult $result
 * @property string|null $notes
 * @property int|null $checked_by
 * @property Carbon|null $checked_at
 * @property int $sort
 * @property-read User|null $checker
 */
class LandCheck extends Model
{
    use BelongsToCompany;

    protected $fillable = ['land_parcel_id', 'key', 'title', 'is_required', 'sort'];

    protected $attributes = ['result' => 'pending'];

    protected function casts(): array
    {
        return ['result' => CheckResult::class, 'is_required' => 'boolean', 'checked_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
