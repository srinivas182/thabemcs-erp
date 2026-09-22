<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A person employed by the company (site workers included; not all employees sign in).
 * The ID number is personal information under POPIA and is encrypted at rest.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $user_id
 * @property string $employee_number
 * @property string|null $payroll_ref
 * @property string $first_name
 * @property string $last_name
 * @property string|null $id_number
 * @property string|null $job_title
 * @property string $employment_type permanent|fixed_term|temporary
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $phone
 * @property string|null $emergency_contact
 * @property int $days_per_week
 * @property string $status active|left
 * @property-read User|null $user
 * @property-read Collection<int, EmployeeAllocation> $allocations
 * @property-read Collection<int, LeaveRequest> $leave
 * @property-read Collection<int, OvertimeEntry> $overtime
 */
class Employee extends Model
{
    use BelongsToCompany, HasPublicUlid, LogsActivity;

    protected $fillable = [
        'user_id', 'employee_number', 'payroll_ref', 'first_name', 'last_name', 'id_number', 'job_title', 'employment_type',
        'start_date', 'end_date', 'phone', 'emergency_contact', 'days_per_week', 'status',
    ];

    protected function casts(): array
    {
        return ['id_number' => 'encrypted', 'start_date' => 'date', 'end_date' => 'date', 'days_per_week' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Never write the ID number into the audit log.
        return LogOptions::defaults()->logOnly(['first_name', 'last_name', 'job_title', 'employment_type', 'status', 'end_date'])->logOnlyDirty();
    }

    public function name(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function maskedIdNumber(): ?string
    {
        $id = $this->id_number;

        return $id === null ? null : str_repeat('•', max(0, strlen($id) - 4)).substr($id, -4);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<EmployeeAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(EmployeeAllocation::class)->latest('from_date');
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leave(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->latest('from_date');
    }

    /**
     * @return HasMany<OvertimeEntry, $this>
     */
    public function overtime(): HasMany
    {
        return $this->hasMany(OvertimeEntry::class)->latest('worked_on');
    }
}
