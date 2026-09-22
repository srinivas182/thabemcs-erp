<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * While someone is away, another person may approve on their behalf.
 *
 * @property int $id
 * @property int $user_id
 * @property int $delegate_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $reason
 * @property-read User $user
 * @property-read User $delegate
 */
class ApprovalDelegation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['user_id', 'delegate_id', 'starts_on', 'ends_on', 'reason'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = Carbon::today('Africa/Johannesburg')->toDateString();

        return $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }
}
