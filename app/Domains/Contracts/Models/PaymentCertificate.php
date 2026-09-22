<?php

declare(strict_types=1);

namespace App\Domains\Contracts\Models;

use App\Domains\Workflow\Contracts\Approvable;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Interim payment certificate: value to date, less retention, less previous certificates = due now.
 *
 * @property int $id
 * @property string $ulid
 * @property int $contract_id
 * @property int $number
 * @property Carbon $valuation_date
 * @property string $gross_value
 * @property string $retention_held
 * @property string $retention_released
 * @property string $previous_certified
 * @property string $amount_due
 * @property string $vat
 * @property string $status draft|pending_approval|certified|rejected
 * @property string|null $notes
 * @property int $prepared_by
 * @property Carbon|null $certified_at
 * @property-read Contract $contract
 * @property-read User $preparer
 */
class PaymentCertificate extends Model implements Approvable
{
    use BelongsToCompany, HasPublicUlid, LogsActivity;

    protected $fillable = [
        'contract_id', 'number', 'valuation_date', 'gross_value', 'retention_held', 'retention_released',
        'previous_certified', 'amount_due', 'vat', 'status', 'notes', 'prepared_by',
    ];

    protected function casts(): array
    {
        return [
            'valuation_date' => 'date', 'certified_at' => 'datetime', 'number' => 'integer',
            'gross_value' => 'decimal:2', 'retention_held' => 'decimal:2', 'retention_released' => 'decimal:2',
            'previous_certified' => 'decimal:2', 'amount_due' => 'decimal:2', 'vat' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'gross_value', 'amount_due'])->logOnlyDirty();
    }

    public function reference(): string
    {
        return sprintf('PC-%03d', $this->number);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvalTitle(): string
    {
        return "{$this->reference()} {$this->contract->supplier->name} ({$this->contract->reference})";
    }

    public function approvalUrl(): string
    {
        return route('projects.contracts', $this->contract->project);
    }

    public function onApprovalGranted(ApprovalRequest $request): void
    {
        $this->forceFill(['status' => 'certified', 'certified_at' => now()])->save();
    }

    public function onApprovalRejected(ApprovalRequest $request, ?string $comment): void
    {
        $this->forceFill(['status' => 'rejected'])->save();
    }
}
