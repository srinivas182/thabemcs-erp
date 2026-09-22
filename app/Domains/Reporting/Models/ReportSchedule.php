<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $report
 * @property array<string, string|null>|null $filters
 * @property string $frequency weekly|monthly
 * @property int $day
 * @property string $format xlsx|csv
 * @property list<int> $recipients
 * @property Carbon|null $last_sent_at
 * @property int $created_by
 * @property-read User $creator
 */
class ReportSchedule extends Model
{
    use BelongsToCompany;

    protected $fillable = ['report', 'filters', 'frequency', 'day', 'format', 'recipients', 'created_by'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'recipients' => 'array', 'day' => 'integer', 'last_sent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDueOn(Carbon $date): bool
    {
        $due = $this->frequency === 'weekly' ? $date->dayOfWeekIso === $this->day : $date->day === $this->day;

        return $due && ($this->last_sent_at === null || ! $this->last_sent_at->copy()->timezone($date->timezone)->isSameDay($date));
    }
}
