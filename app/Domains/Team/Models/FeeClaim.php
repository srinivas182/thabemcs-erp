<?php

declare(strict_types=1);

namespace App\Domains\Team\Models;

use App\Domains\Team\Enums\ClaimStatus;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A professional's claim for fees for work done.
 *
 * @property int $id
 * @property int $professional_appointment_id
 * @property string $claim_number
 * @property string|null $description
 * @property string $amount
 * @property Carbon $submitted_on
 * @property ClaimStatus $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_on
 * @property-read ProfessionalAppointment $appointment
 */
class FeeClaim extends Model
{
    use BelongsToCompany, LogsActivity;

    protected $fillable = ['professional_appointment_id', 'claim_number', 'description', 'amount', 'submitted_on'];

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'amount' => 'decimal:2',
            'submitted_on' => 'date',
            'approved_at' => 'datetime',
            'paid_on' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['claim_number', 'amount', 'status', 'paid_on'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<ProfessionalAppointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ProfessionalAppointment::class, 'professional_appointment_id');
    }
}
