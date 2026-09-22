<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $type
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string $days
 * @property string $status pending|approved|declined|cancelled
 * @property string|null $notes
 * @property int|null $decided_by
 * @property-read Employee $employee
 */
class LeaveRequest extends Model
{
    use BelongsToCompany;

    protected $fillable = ['employee_id', 'type', 'from_date', 'to_date', 'days', 'status', 'notes', 'decided_by'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'days' => 'decimal:1'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
